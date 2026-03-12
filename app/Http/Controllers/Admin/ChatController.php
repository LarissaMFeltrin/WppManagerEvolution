<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\ContactAlias;
use App\Models\Conversa;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    protected EvolutionApiService $evolution;
    protected WhatsAppService $whatsapp;

    public function __construct(EvolutionApiService $evolution, WhatsAppService $whatsapp)
    {
        $this->evolution = $evolution;
        $this->whatsapp = $whatsapp;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $conversas = Conversa::whereIn('account_id', $accountIds)
            ->where('atendente_id', $user->id)
            ->where('status', 'em_atendimento')
            ->with(['account', 'chat.messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('ultima_msg_em', 'desc')
            ->get();

        $filaCount = Conversa::whereIn('account_id', $accountIds)
            ->where('status', 'aguardando')
            ->count();

        $maxSlots = $user->max_conversas_simultaneas ?? 8;
        $conversasAtivas = $conversas->count();

        $atendentes = \App\Models\User::where('empresa_id', $user->empresa_id)
            ->where('role', 'agent')
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('admin.chat.index', compact(
            'conversas',
            'user',
            'filaCount',
            'maxSlots',
            'conversasAtivas',
            'atendentes'
        ));
    }

    public function fila()
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $conversas = Conversa::whereIn('account_id', $accountIds)
            ->where('status', 'aguardando')
            ->with(['account', 'chat.messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('cliente_aguardando_desde', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $conversasAtivas = Conversa::whereIn('account_id', $accountIds)
            ->where('atendente_id', $user->id)
            ->where('status', 'em_atendimento')
            ->count();

        $maxSlots = $user->max_conversas_simultaneas ?? 8;

        return view('admin.chat.fila', compact('conversas', 'user', 'conversasAtivas', 'maxSlots'));
    }

    public function filaDados()
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $conversas = Conversa::whereIn('account_id', $accountIds)
            ->where('status', 'aguardando')
            ->with(['account', 'chat.messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('cliente_aguardando_desde', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $result = $conversas->map(function ($conversa) {
            $ultimaMensagem = $conversa->chat?->messages?->first();
            $tempoNaFila = $conversa->cliente_aguardando_desde ?? $conversa->created_at;
            $diffHoras = $tempoNaFila->diffInHours(now());
            $diffMinutos = $tempoNaFila->diffInMinutes(now()) % 60;

            return [
                'id' => $conversa->id,
                'cliente_nome' => $conversa->cliente_nome,
                'cliente_numero' => $conversa->cliente_numero,
                'ultima_mensagem' => $ultimaMensagem?->message_text ?? '',
                'tempo_na_fila' => $diffHoras . 'h ' . $diffMinutos . 'min',
                'instancia' => $conversa->account?->session_name,
            ];
        });

        return response()->json(['conversas' => $result]);
    }

    public function painel()
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $conversas = Conversa::whereIn('account_id', $accountIds)
            ->where('atendente_id', $user->id)
            ->where('status', 'em_atendimento')
            ->with(['account', 'chat.messages' => function ($q) {
                // Pega as 500 mais recentes (DESC) para depois inverter na view
                $q->orderBy('timestamp', 'desc')->limit(500);
            }])
            ->orderBy('ultima_msg_em', 'desc')
            ->limit(8)
            ->get();

        $filaCount = Conversa::whereIn('account_id', $accountIds)
            ->where('status', 'aguardando')
            ->count();

        $maxSlots = $user->max_conversas_simultaneas ?? 8;
        $slotsUsados = $conversas->count();
        $slotsDisponiveis = $maxSlots - $slotsUsados;

        return view('admin.chat.painel', compact(
            'conversas',
            'filaCount',
            'maxSlots',
            'slotsUsados',
            'slotsDisponiveis',
            'user'
        ));
    }

    /**
     * Iniciar nova conversa com um número
     */
    public function novaConversa(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:whatsapp_accounts,id',
            'numero' => 'required|string|max:50',
            'nome' => 'nullable|string|max:255',
            'mensagem' => 'required|string|max:4096',
        ]);

        $user = Auth::user();
        $account = WhatsappAccount::findOrFail($validated['account_id']);

        // Verificar se a conta pertence à empresa do usuário
        if ($account->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        // Limpar número (remover espaços, traços, etc)
        $numero = preg_replace('/[^0-9]/', '', $validated['numero']);
        $jid = $numero . '@s.whatsapp.net';
        $nome = $validated['nome'] ?: $numero;

        try {
            // 1. Enviar a mensagem primeiro
            $result = $this->evolution->sendText(
                $account->session_name,
                $numero,
                $validated['mensagem']
            );

            // 2. Criar ou buscar o chat
            $chat = Chat::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'chat_id' => $jid,
                ],
                [
                    'chat_name' => $nome,
                    'chat_type' => 'individual',
                    'last_message_timestamp' => now()->timestamp,
                ]
            );

            // 3. Criar ou buscar contato
            Contact::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'jid' => $jid,
                ],
                [
                    'name' => $nome,
                    'phone_number' => $numero,
                ]
            );

            // 4. Criar conversa para atendimento
            $conversa = Conversa::create([
                'account_id' => $account->id,
                'chat_id' => $chat->id,
                'cliente_numero' => $numero,
                'cliente_nome' => $nome,
                'status' => 'em_atendimento',
                'atendente_id' => $user->id,
                'atendido_em' => now(),
                'ultima_msg_em' => now(),
            ]);

            // 5. Salvar a mensagem enviada
            if (isset($result['key'])) {
                Message::create([
                    'chat_id' => $chat->id,
                    'message_key' => $result['key']['id'] ?? uniqid('msg_'),
                    'from_jid' => $account->owner_jid ?? ($account->phone_number . '@s.whatsapp.net'),
                    'to_jid' => $jid,
                    'message_text' => $validated['mensagem'],
                    'message_type' => 'text',
                    'is_from_me' => true,
                    'sent_by_user_id' => $user->id,
                    'timestamp' => now()->timestamp,
                    'status' => 'sent',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Conversa iniciada com {$nome}!",
                'conversa_id' => $conversa->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao enviar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Enviar mensagem (alias para compatibilidade)
     */
    public function enviar(Request $request, Conversa $conversa)
    {
        return $this->enviarAjax($request, $conversa);
    }

    /**
     * Enviar mensagem de texto
     */
    public function enviarAjax(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'mensagem' => 'required|string|max:4096',
            'quoted_message_id' => 'nullable|string',
        ]);

        try {
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');
            $quotedId = $validated['quoted_message_id'] ?? null;

            // Obter JID do remetente (conta WhatsApp)
            $fromJid = $conversa->account->owner_jid
                ?? ($conversa->account->phone_number . '@s.whatsapp.net');

            // Buscar informações da mensagem citada (se houver)
            $quotedFromMe = false;
            if ($quotedId) {
                $quotedMsg = Message::where('message_key', $quotedId)->first();
                $quotedFromMe = $quotedMsg?->is_from_me ?? false;
            }

            // Extrair número do JID para envio (usar JID do chat, não cliente_numero que pode ser LID)
            $numeroParaEnvio = explode('@', $jid)[0];

            // Enviar via Evolution API (com ou sem citação)
            $result = $this->evolution->sendText(
                $conversa->account->session_name,
                $numeroParaEnvio,
                $validated['mensagem'],
                $quotedId,    // ID da mensagem citada (ou null)
                $jid,         // remoteJid para a citação
                $quotedFromMe // se a mensagem citada foi enviada por mim
            );

            // Verificar se o envio foi bem sucedido
            if (!($result['success'] ?? false)) {
                $errorMsg = $result['error'] ?? $result['message'] ?? 'Falha ao enviar mensagem';
                Log::error('Erro ao enviar mensagem', ['result' => $result, 'conversa' => $conversa->id]);
                return response()->json(['error' => $errorMsg], 500);
            }

            // Salvar mensagem localmente
            $dbMessage = null;
            if ($conversa->chat) {
                $messageKey = $result['data']['key']['id'] ?? $result['key']['id'] ?? uniqid();

                // Buscar texto da mensagem citada (reutilizar $quotedMsg se já foi buscado)
                $quotedText = null;
                if ($quotedId && isset($quotedMsg) && $quotedMsg) {
                    $quotedText = $quotedMsg->message_text ?? '[Mídia]';
                }

                $dbMessage = Message::create([
                    'chat_id' => $conversa->chat->id,
                    'message_key' => $messageKey,
                    'from_jid' => $fromJid,
                    'to_jid' => $jid,
                    'message_text' => $validated['mensagem'],
                    'message_type' => 'text',
                    'is_from_me' => true,
                    'sent_by_user_id' => Auth::id(),
                    'status' => 'sent',
                    'timestamp' => time(),
                    'quoted_message_id' => $quotedId,
                    'quoted_text' => $quotedText,
                ]);
            }

            $conversa->update(['ultima_msg_em' => now()]);

            return response()->json([
                'success' => true,
                'message' => $dbMessage ? [
                    'id' => $dbMessage->id,
                    'message_key' => $dbMessage->message_key,
                    'message_text' => $dbMessage->message_text,
                    'message_type' => $dbMessage->message_type,
                    'is_from_me' => true,
                    'created_at' => $dbMessage->created_at->format('H:i'),
                    'quoted_text' => $dbMessage->quoted_text,
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar imagem
     */
    public function enviarImagem(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'imagem' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:16384',
            'caption' => 'nullable|string|max:1024',
        ]);

        try {
            // Salvar arquivo
            $file = $request->file('imagem');
            $path = $file->store("media/{$conversa->account_id}/sent", 'public');
            $publicUrl = Storage::url($path);

            // Converter para base64
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();

            // Usar JID do chat (não cliente_numero que pode ser LID)
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');
            $numeroParaEnvio = explode('@', $jid)[0];

            // Enviar via Evolution usando base64
            $result = $this->evolution->sendImageBase64(
                $conversa->account->session_name,
                $numeroParaEnvio,
                $base64,
                $mimeType,
                $validated['caption'] ?? null
            );

            // Verificar se o envio foi bem sucedido
            if (!($result['success'] ?? false)) {
                // Remover arquivo se falhou
                Storage::disk('public')->delete($path);
                $errorMsg = $result['error'] ?? 'Falha ao enviar imagem';
                Log::error('Erro ao enviar imagem', ['result' => $result, 'conversa' => $conversa->id]);
                return response()->json(['error' => $errorMsg], 500);
            }

            // Salvar no banco
            $dbMessage = null;
            if ($conversa->chat) {
                $messageKey = $result['data']['key']['id'] ?? $result['key']['id'] ?? uniqid();

                $dbMessage = Message::create([
                    'chat_id' => $conversa->chat->id,
                    'message_key' => $messageKey,
                    'from_jid' => $conversa->account->owner_jid ?? ($conversa->account->phone_number . '@s.whatsapp.net'),
                    'to_jid' => $conversa->chat->chat_id,
                    'message_text' => $validated['caption'] ?? null,
                    'message_type' => 'image',
                    'media_url' => $publicUrl,
                    'media_mime_type' => $file->getMimeType(),
                    'is_from_me' => true,
                    'sent_by_user_id' => Auth::id(),
                    'status' => 'sent',
                    'timestamp' => time(),
                ]);
            }

            $conversa->update(['ultima_msg_em' => now()]);

            return response()->json([
                'success' => true,
                'message' => $dbMessage ? [
                    'id' => $dbMessage->id,
                    'message_key' => $dbMessage->message_key,
                    'message_text' => $dbMessage->message_text,
                    'message_type' => 'image',
                    'media_url' => $dbMessage->media_url,
                    'is_from_me' => true,
                    'created_at' => $dbMessage->created_at->format('H:i'),
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar documento
     */
    public function enviarDocumento(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'documento' => 'required|file|max:65536', // 64MB
        ]);

        try {
            $file = $request->file('documento');
            $originalName = $file->getClientOriginalName();
            $path = $file->store("media/{$conversa->account_id}/sent", 'public');
            $publicUrl = Storage::url($path);

            // Converter para base64
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();

            // Usar JID do chat (não cliente_numero que pode ser LID)
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');
            $numeroParaEnvio = explode('@', $jid)[0];

            $result = $this->evolution->sendDocumentBase64(
                $conversa->account->session_name,
                $numeroParaEnvio,
                $base64,
                $mimeType,
                $originalName
            );

            // Verificar se o envio foi bem sucedido
            if (!($result['success'] ?? false)) {
                Storage::disk('public')->delete($path);
                $errorMsg = $result['error'] ?? 'Falha ao enviar documento';
                Log::error('Erro ao enviar documento', ['result' => $result, 'conversa' => $conversa->id]);
                return response()->json(['error' => $errorMsg], 500);
            }

            $dbMessage = null;
            if ($conversa->chat) {
                $messageKey = $result['data']['key']['id'] ?? $result['key']['id'] ?? uniqid();

                $dbMessage = Message::create([
                    'chat_id' => $conversa->chat->id,
                    'message_key' => $messageKey,
                    'from_jid' => $conversa->account->owner_jid ?? ($conversa->account->phone_number . '@s.whatsapp.net'),
                    'to_jid' => $conversa->chat->chat_id,
                    'message_text' => $originalName,
                    'message_type' => 'document',
                    'media_url' => $publicUrl,
                    'media_mime_type' => $file->getMimeType(),
                    'media_filename' => $originalName,
                    'is_from_me' => true,
                    'sent_by_user_id' => Auth::id(),
                    'status' => 'sent',
                    'timestamp' => time(),
                ]);
            }

            $conversa->update(['ultima_msg_em' => now()]);

            return response()->json([
                'success' => true,
                'message' => $dbMessage ? [
                    'id' => $dbMessage->id,
                    'message_key' => $dbMessage->message_key,
                    'message_text' => $originalName,
                    'message_type' => 'document',
                    'media_url' => $dbMessage->media_url,
                    'is_from_me' => true,
                    'created_at' => $dbMessage->created_at->format('H:i'),
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar áudio
     */
    public function enviarAudio(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'audio' => 'required|file|mimes:ogg,mp3,m4a,wav,webm|max:16384',
        ]);

        try {
            $file = $request->file('audio');
            $path = $file->store("media/{$conversa->account_id}/sent", 'public');
            $publicUrl = Storage::url($path);

            // Converter para base64
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();

            // Usar JID do chat (não cliente_numero que pode ser LID)
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');
            $numeroParaEnvio = explode('@', $jid)[0];

            $result = $this->evolution->sendAudioBase64(
                $conversa->account->session_name,
                $numeroParaEnvio,
                $base64,
                $mimeType
            );

            // Verificar se o envio foi bem sucedido
            if (!($result['success'] ?? false)) {
                Storage::disk('public')->delete($path);
                $errorMsg = $result['error'] ?? 'Falha ao enviar audio';
                Log::error('Erro ao enviar audio', ['result' => $result, 'conversa' => $conversa->id]);
                return response()->json(['error' => $errorMsg], 500);
            }

            $dbMessage = null;
            if ($conversa->chat) {
                $messageKey = $result['data']['key']['id'] ?? $result['key']['id'] ?? uniqid();

                $dbMessage = Message::create([
                    'chat_id' => $conversa->chat->id,
                    'message_key' => $messageKey,
                    'from_jid' => $conversa->account->owner_jid ?? ($conversa->account->phone_number . '@s.whatsapp.net'),
                    'to_jid' => $conversa->chat->chat_id,
                    'message_type' => 'audio',
                    'media_url' => $publicUrl,
                    'media_mime_type' => $file->getMimeType(),
                    'is_from_me' => true,
                    'sent_by_user_id' => Auth::id(),
                    'status' => 'sent',
                    'timestamp' => time(),
                ]);
            }

            $conversa->update(['ultima_msg_em' => now()]);

            return response()->json([
                'success' => true,
                'message' => $dbMessage ? [
                    'id' => $dbMessage->id,
                    'message_key' => $dbMessage->message_key,
                    'message_type' => 'audio',
                    'media_url' => $dbMessage->media_url,
                    'is_from_me' => true,
                    'created_at' => $dbMessage->created_at->format('H:i'),
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar vídeo
     */
    public function enviarVideo(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'video' => 'required|file|mimes:mp4,3gp,mov|max:65536',
            'caption' => 'nullable|string|max:1024',
        ]);

        try {
            $file = $request->file('video');
            $path = $file->store("media/{$conversa->account_id}/sent", 'public');
            $publicUrl = Storage::url($path);

            // Converter para base64
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();

            // Usar JID do chat (não cliente_numero que pode ser LID)
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');
            $numeroParaEnvio = explode('@', $jid)[0];

            $result = $this->evolution->sendVideoBase64(
                $conversa->account->session_name,
                $numeroParaEnvio,
                $base64,
                $mimeType,
                $validated['caption'] ?? null
            );

            // Verificar se o envio foi bem sucedido
            if (!($result['success'] ?? false)) {
                Storage::disk('public')->delete($path);
                $errorMsg = $result['error'] ?? 'Falha ao enviar video';
                Log::error('Erro ao enviar video', ['result' => $result, 'conversa' => $conversa->id]);
                return response()->json(['error' => $errorMsg], 500);
            }

            $dbMessage = null;
            if ($conversa->chat) {
                $messageKey = $result['data']['key']['id'] ?? $result['key']['id'] ?? uniqid();

                $dbMessage = Message::create([
                    'chat_id' => $conversa->chat->id,
                    'message_key' => $messageKey,
                    'from_jid' => $conversa->account->owner_jid ?? ($conversa->account->phone_number . '@s.whatsapp.net'),
                    'to_jid' => $conversa->chat->chat_id,
                    'message_text' => $validated['caption'] ?? null,
                    'message_type' => 'video',
                    'media_url' => $publicUrl,
                    'media_mime_type' => $file->getMimeType(),
                    'is_from_me' => true,
                    'sent_by_user_id' => Auth::id(),
                    'status' => 'sent',
                    'timestamp' => time(),
                ]);
            }

            $conversa->update(['ultima_msg_em' => now()]);

            return response()->json([
                'success' => true,
                'message' => $dbMessage ? [
                    'id' => $dbMessage->id,
                    'message_key' => $dbMessage->message_key,
                    'message_text' => $dbMessage->message_text,
                    'message_type' => 'video',
                    'media_url' => $dbMessage->media_url,
                    'is_from_me' => true,
                    'created_at' => $dbMessage->created_at->format('H:i'),
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reagir a uma mensagem
     */
    public function reagir(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'message_key' => 'required|string',
            'emoji' => 'required|string|max:10',
        ]);

        try {
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');

            // Buscar mensagem para saber se é fromMe
            $message = Message::where('message_key', $validated['message_key'])
                ->orWhere('message_key', 'like', '%' . $validated['message_key'])
                ->first();

            $fromMe = $message ? $message->is_from_me : false;

            // Tentar via Evolution primeiro
            $result = $this->evolution->sendReaction(
                $conversa->account->session_name,
                $jid,
                $validated['message_key'],
                $validated['emoji'],
                $fromMe
            );

            // Atualizar reações no banco (substituir reação anterior do mesmo remetente)
            if ($message) {
                $reactions = $message->reactions ?? [];

                // Remover reação anterior do mesmo remetente ('me')
                $reactions = array_filter($reactions, fn($r) => ($r['from'] ?? '') !== 'me');

                // Adicionar nova reação
                $reactions[] = [
                    'emoji' => $validated['emoji'],
                    'from' => 'me',
                    'timestamp' => time(),
                ];

                $message->update(['reactions' => array_values($reactions)]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Deletar mensagem
     */
    public function deletar(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'message_key' => 'required|string',
        ]);

        try {
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');

            // Usar Evolution API
            $result = $this->evolution->deleteMessage(
                $conversa->account->session_name,
                $jid,
                $validated['message_key'],
                true // fromMe
            );

            // Atualizar no banco independente do resultado da API
            Message::where('message_key', $validated['message_key'])
                ->update(['is_deleted' => true]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Editar mensagem
     */
    public function editar(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'message_key' => 'required|string',
            'new_text' => 'required|string|max:4096',
        ]);

        try {
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');

            // Usar Evolution API
            $result = $this->evolution->editMessage(
                $conversa->account->session_name,
                $jid,
                $validated['message_key'],
                $validated['new_text']
            );

            // Atualizar no banco
            Message::where('message_key', $validated['message_key'])
                ->update([
                    'message_text' => $validated['new_text'],
                    'is_edited' => true,
                ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Encaminhar mensagem
     */
    public function encaminhar(Request $request, Conversa $conversa)
    {
        $validated = $request->validate([
            'message_key' => 'required|string',
            'target_conversa_id' => 'required|exists:conversas,id',
        ]);

        try {
            $targetConversa = Conversa::findOrFail($validated['target_conversa_id']);
            $toJid = $targetConversa->chat->chat_id ?? ($targetConversa->cliente_numero . '@s.whatsapp.net');

            // Buscar a mensagem original
            $originalMessage = Message::where('message_key', $validated['message_key'])
                ->orWhere('message_key', 'like', '%' . $validated['message_key'])
                ->first();

            if (!$originalMessage) {
                return response()->json(['error' => 'Mensagem não encontrada'], 404);
            }

            // Preparar texto para encaminhar
            $forwardText = '';
            $result = null;

            if ($originalMessage->message_type === 'text') {
                $forwardText = '↪️ ' . $originalMessage->message_text;
                $result = $this->evolution->sendText(
                    $conversa->account->session_name,
                    $toJid,
                    $forwardText
                );
            } elseif (in_array($originalMessage->message_type, ['image', 'video', 'audio', 'document'])) {
                $forwardText = '↪️ [' . ucfirst($originalMessage->message_type) . ' encaminhado]';
                if ($originalMessage->message_text) {
                    $forwardText .= "\n" . $originalMessage->message_text;
                }
                $result = $this->evolution->sendText(
                    $conversa->account->session_name,
                    $toJid,
                    $forwardText
                );
            } else {
                return response()->json(['error' => 'Tipo de mensagem não suportado'], 400);
            }

            // Salvar mensagem encaminhada no banco
            $ownerJid = $conversa->account->owner_jid
                ?? (preg_replace('/\D/', '', $conversa->account->phone_number) . '@s.whatsapp.net');

            $messageKey = $result['data']['key']['id'] ?? ('fwd_' . time() . '_' . uniqid());

            // Garantir que o chat de destino existe
            $targetChat = $targetConversa->chat;
            if (!$targetChat) {
                $targetChat = Chat::firstOrCreate(
                    ['chat_id' => $toJid, 'account_id' => $conversa->account->id],
                    ['chat_type' => 'individual']
                );
                $targetConversa->update(['chat_id' => $targetChat->id]);
            }

            $newMessage = Message::create([
                'chat_id' => $targetChat->id,
                'message_key' => $messageKey,
                'from_jid' => $ownerJid,
                'to_jid' => $toJid,
                'message_text' => $forwardText,
                'message_type' => 'text',
                'is_from_me' => true,
                'sent_by_user_id' => Auth::id(),
                'timestamp' => time(),
                'status' => 'sent',
            ]);

            // Atualizar timestamp da conversa de destino
            $targetConversa->update(['ultima_msg_em' => now()]);

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $newMessage->id,
                    'message_key' => $newMessage->message_key,
                    'message_text' => $newMessage->message_text,
                    'message_type' => 'text',
                    'is_from_me' => true,
                    'created_at' => $newMessage->created_at->format('H:i'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Buscar mensagens de uma conversa
     */
    public function mensagens(Request $request, Conversa $conversa)
    {
        if (!$conversa->chat) {
            return response()->json(['messages' => []]);
        }

        $query = $conversa->chat->messages();

        // Se passou after_id, retorna apenas mensagens mais novas
        $afterId = $request->query('after_id');
        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query
            ->orderBy('id', $afterId ? 'asc' : 'desc')
            ->limit($afterId ? 100 : 500)
            ->get();

        // Se não é busca incremental, inverte para ordem cronológica
        if (!$afterId) {
            $messages = $messages->reverse();
        }

        $messages = $messages->values()->map(function ($msg) {
            return [
                'id' => $msg->id,
                'message_key' => $msg->message_key,
                'message_text' => $msg->message_text,
                'message_type' => $msg->message_type,
                'is_from_me' => $msg->is_from_me,
                'is_deleted' => $msg->is_deleted,
                'is_edited' => $msg->is_edited,
                'media_url' => $msg->media_url,
                'media_filename' => $msg->media_filename,
                'media_duration' => $msg->media_duration,
                'sender_name' => $msg->sender_name,
                'quoted_text' => $msg->quoted_text,
                'reactions' => $msg->reactions,
                'created_at' => $msg->message_time,
                'message_date' => $msg->message_date,
            ];
        });

        return response()->json(['messages' => $messages]);
    }

    /**
     * Finalizar conversa
     */
    public function finalizarAjax(Conversa $conversa)
    {
        $conversa->update([
            'status' => 'finalizada',
            'finalizada_em' => now(),
        ]);

        if ($conversa->atendente_id) {
            \App\Models\User::where('id', $conversa->atendente_id)->decrement('conversas_ativas');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Devolver conversa para fila
     */
    public function devolver(Conversa $conversa)
    {
        $conversa->update([
            'status' => 'aguardando',
            'atendente_id' => null,
            'cliente_aguardando_desde' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Marcar como lido
     */
    public function marcarLido(Conversa $conversa)
    {
        try {
            $jid = $conversa->chat->chat_id ?? ($conversa->cliente_numero . '@s.whatsapp.net');

            $this->evolution->markAsRead($conversa->account->session_name, $jid);

            if ($conversa->chat) {
                $conversa->chat->update(['unread_count' => 0]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar status de digitando
     */
    public function digitando(Conversa $conversa)
    {
        try {
            $this->whatsapp->sendTyping(
                $conversa->account->session_name,
                $conversa->cliente_numero
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false]);
        }
    }

    /**
     * Sincronizar histórico de mensagens de uma conversa
     */
    public function sincronizarHistorico(Conversa $conversa, Request $request)
    {
        try {
            $limit = $request->input('limit', 50);
            $chatJid = $conversa->chat->chat_id ?? null;
            $clienteNumero = $conversa->cliente_numero;
            $numeroReal = $request->input('numero_real'); // Número informado manualmente

            if (!$chatJid && !$clienteNumero && !$numeroReal) {
                return response()->json(['error' => 'Chat JID não encontrado'], 400);
            }

            // Montar lista de JIDs aceitos (LID + número real + número informado)
            $acceptedJids = [];
            if ($chatJid) {
                $acceptedJids[] = $chatJid;
            }
            if ($clienteNumero) {
                $acceptedJids[] = $clienteNumero . '@s.whatsapp.net';
                $numeroLimpo = preg_replace('/\D/', '', $clienteNumero);
                if ($numeroLimpo !== $clienteNumero) {
                    $acceptedJids[] = $numeroLimpo . '@s.whatsapp.net';
                }
            }
            // Número informado manualmente pelo usuário
            if ($numeroReal) {
                $numeroLimpo = preg_replace('/\D/', '', $numeroReal);
                $acceptedJids[] = $numeroLimpo . '@s.whatsapp.net';
            }

            // Buscar aliases vinculados a este chat (LID <-> número)
            if ($conversa->chat) {
                $aliases = ContactAlias::where('primary_chat_id', $conversa->chat->id)
                    ->pluck('alias_jid')
                    ->toArray();
                $acceptedJids = array_merge($acceptedJids, $aliases);
            }

            $acceptedJids = array_unique($acceptedJids);

            // Buscar mensagens de cada JID aceito
            $messages = [];
            $primaryJid = $chatJid ?: ($clienteNumero . '@s.whatsapp.net');

            // Primeiro buscar do JID principal
            $result = $this->evolution->fetchMessages(
                $conversa->account->session_name,
                $primaryJid,
                $limit
            );

            if ($result['success'] && !empty($result['data'])) {
                $messages = array_merge($messages, $result['data']);
            }

            // Se tiver aliases (LID), buscar deles também
            foreach ($acceptedJids as $jid) {
                if ($jid !== $primaryJid && strpos($jid, '@lid') !== false) {
                    $aliasResult = $this->evolution->fetchMessages(
                        $conversa->account->session_name,
                        $jid,
                        $limit
                    );
                    if ($aliasResult['success'] && !empty($aliasResult['data'])) {
                        $messages = array_merge($messages, $aliasResult['data']);
                    }
                }
            }
            $imported = 0;
            $skipped = 0;
            $wrongChat = 0;

            foreach ($messages as $messageData) {
                $key = $messageData['key'] ?? [];
                $messageId = $key['id'] ?? null;
                $remoteJid = $key['remoteJid'] ?? null;

                if (!$messageId) {
                    $skipped++;
                    continue;
                }

                // Validar se a mensagem pertence a este chat (qualquer JID aceito)
                if (!in_array($remoteJid, $acceptedJids)) {
                    $wrongChat++;
                    continue;
                }

                // Verificar se já existe (ID pode ser parcial ou com prefixo diferente)
                if (Message::where('message_key', $messageId)
                    ->orWhere('message_key', 'like', '%' . $messageId)
                    ->exists()) {
                    $skipped++;
                    continue;
                }

                // Atingiu o limite desejado
                if ($imported >= $limit) {
                    break;
                }

                // Processar mensagem
                $this->processHistoryMessage($conversa, $messageData);
                $imported++;
            }

            Log::info('Sincronização de histórico', [
                'conversa' => $conversa->id,
                'accepted_jids' => $acceptedJids,
                'imported' => $imported,
                'skipped' => $skipped,
                'wrong_chat' => $wrongChat,
            ]);

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'total' => count($messages),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar histórico', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sincronizar todos os chats de uma conta WhatsApp
     */
    public function sincronizarChats(WhatsappAccount $account)
    {
        try {
            $user = Auth::user();

            // Verificar se conta pertence à empresa
            if ($account->empresa_id !== $user->empresa_id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $result = $this->evolution->fetchChats($account->session_name);

            if (!$result['success']) {
                return response()->json(['error' => $result['error'] ?? 'Erro ao buscar chats'], 500);
            }

            $chats = $result['data'] ?? [];
            $created = 0;
            $updated = 0;

            foreach ($chats as $chatData) {
                $remoteJid = $chatData['id'] ?? $chatData['remoteJid'] ?? null;
                if (!$remoteJid) continue;

                $isGroup = str_contains($remoteJid, '@g.us');
                $chatName = $chatData['name'] ?? $chatData['pushName'] ?? $chatData['subject'] ?? $this->extractPhoneFromJid($remoteJid);

                $chat = \App\Models\Chat::where('account_id', $account->id)
                    ->where('chat_id', $remoteJid)
                    ->first();

                if ($chat) {
                    $chat->update([
                        'chat_name' => $chatName,
                        'unread_count' => $chatData['unreadCount'] ?? 0,
                    ]);
                    $updated++;
                } else {
                    \App\Models\Chat::create([
                        'account_id' => $account->id,
                        'chat_id' => $remoteJid,
                        'chat_name' => $chatName,
                        'chat_type' => $isGroup ? 'group' : 'individual',
                        'unread_count' => $chatData['unreadCount'] ?? 0,
                    ]);
                    $created++;
                }
            }

            $account->update(['last_full_sync' => now()]);

            return response()->json([
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => count($chats),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar chats', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Processar mensagem do histórico
     */
    protected function processHistoryMessage(Conversa $conversa, array $messageData)
    {
        $key = $messageData['key'] ?? [];
        $message = $messageData['message'] ?? [];

        $remoteJid = $key['remoteJid'] ?? null;
        $messageId = $key['id'] ?? null;
        $fromMe = $key['fromMe'] ?? false;
        $participant = $key['participant'] ?? null;

        $isGroup = str_contains($remoteJid ?? '', '@g.us');
        $senderName = $messageData['pushName'] ?? null;

        // Determinar tipo e conteúdo
        $messageType = 'text';
        $messageText = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaFilename = null;

        if (isset($message['conversation'])) {
            $messageText = $message['conversation'];
        } elseif (isset($message['extendedTextMessage'])) {
            $messageText = $message['extendedTextMessage']['text'] ?? null;
        } elseif (isset($message['imageMessage'])) {
            $messageType = 'image';
            $messageText = $message['imageMessage']['caption'] ?? null;
            $mediaMimeType = $message['imageMessage']['mimetype'] ?? 'image/jpeg';
        } elseif (isset($message['videoMessage'])) {
            $messageType = 'video';
            $messageText = $message['videoMessage']['caption'] ?? null;
            $mediaMimeType = $message['videoMessage']['mimetype'] ?? 'video/mp4';
        } elseif (isset($message['audioMessage'])) {
            $messageType = 'audio';
            $mediaMimeType = $message['audioMessage']['mimetype'] ?? 'audio/ogg';
        } elseif (isset($message['documentMessage'])) {
            $messageType = 'document';
            $mediaFilename = $message['documentMessage']['fileName'] ?? 'documento';
            $messageText = $mediaFilename;
            $mediaMimeType = $message['documentMessage']['mimetype'] ?? 'application/octet-stream';
        } elseif (isset($message['stickerMessage'])) {
            $messageType = 'sticker';
        } elseif (isset($message['locationMessage'])) {
            $messageType = 'location';
        } elseif (isset($message['contactMessage'])) {
            $messageType = 'contact';
            $messageText = $message['contactMessage']['displayName'] ?? 'Contato';
        }

        // Determinar JID do remetente
        $senderJid = $fromMe
            ? $conversa->account->owner_jid
            : ($isGroup ? $participant : $remoteJid);

        // Criar mensagem
        Message::create([
            'chat_id' => $conversa->chat->id,
            'message_key' => $messageId,
            'from_jid' => $senderJid ?? $remoteJid,
            'sender_name' => $fromMe ? null : $senderName,
            'participant_jid' => $isGroup ? $participant : null,
            'to_jid' => $fromMe ? $remoteJid : $conversa->account->owner_jid,
            'message_text' => $messageText,
            'message_type' => $messageType,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_filename' => $mediaFilename,
            'is_from_me' => $fromMe,
            'timestamp' => $messageData['messageTimestamp'] ?? time(),
            'status' => 'delivered',
            'message_raw' => $messageData,
        ]);
    }

    protected function extractPhoneFromJid(string $jid): string
    {
        return explode('@', $jid)[0];
    }

    /**
     * Baixar mídias pendentes de uma conversa
     */
    public function baixarMidias(Conversa $conversa)
    {
        try {
            if (!$conversa->chat) {
                return response()->json(['error' => 'Chat não encontrado'], 400);
            }

            // Buscar mensagens de mídia sem URL
            // Limite baixo (5) para não desconectar o WhatsApp
            $mensagens = Message::where('chat_id', $conversa->chat->id)
                ->whereIn('message_type', ['image', 'video', 'audio', 'document', 'sticker'])
                ->whereNull('media_url')
                ->limit(5)
                ->get();

            $downloaded = 0;
            $failed = 0;

            foreach ($mensagens as $msg) {
                try {
                    // Pausa entre downloads para não sobrecarregar
                    if ($downloaded > 0) {
                        usleep(500000); // 0.5 segundo entre cada download
                    }

                    // Passar key completo: id, remoteJid, fromMe
                    $result = $this->evolution->downloadMedia(
                        $conversa->account->session_name,
                        $msg->message_key,
                        $conversa->chat->chat_id,  // remoteJid
                        $msg->is_from_me           // fromMe
                    );

                    if ($result['success'] && !empty($result['data']['base64'])) {
                        $base64 = $result['data']['base64'];
                        $mimeType = $result['data']['mimetype'] ?? $msg->media_mime_type ?? 'application/octet-stream';
                        $extension = $this->getExtensionFromMimeType($mimeType);

                        $filename = $msg->message_key . '.' . $extension;
                        $path = "media/{$conversa->account_id}/{$msg->message_type}/{$filename}";

                        Storage::disk('public')->put($path, base64_decode($base64));

                        $msg->update([
                            'media_url' => Storage::url($path),
                            'media_mime_type' => $mimeType,
                        ]);

                        $downloaded++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao baixar mídia', [
                        'message_key' => $msg->message_key,
                        'error' => $e->getMessage()
                    ]);
                    $failed++;
                }
            }

            return response()->json([
                'success' => true,
                'downloaded' => $downloaded,
                'failed' => $failed,
                'pending' => Message::where('chat_id', $conversa->chat->id)
                    ->whereIn('message_type', ['image', 'video', 'audio', 'document', 'sticker'])
                    ->whereNull('media_url')
                    ->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao baixar mídias', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
        ];

        return $map[$mimeType] ?? 'bin';
    }
}
