<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\ContactAlias;
use App\Models\Conversa;
use App\Models\WhatsappAccount;
use App\Services\ChatMergeService;
use App\Services\EvolutionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $query = Contact::with('account')
            ->whereIn('account_id', $accountIds);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('jid', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filtro para contatos sem nome
        if ($request->filter === 'sem_nome') {
            $query->where(function ($q) {
                $q->whereNull('name')
                    ->orWhere('name', '')
                    ->orWhere('name', 'Sem nome');
            });
        }

        $contacts = $query->orderBy('name')->paginate(30)->withQueryString();

        $accounts = WhatsappAccount::whereIn('id', $accountIds)->orderBy('session_name')->get();

        return view('admin.contacts.index', compact('contacts', 'accounts'));
    }

    public function edit(Contact $contact)
    {
        return view('admin.contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $contact->update($validated);

        return redirect()->route('admin.contatos.index')
            ->with('success', 'Contato atualizado!');
    }

    public function sincronizarPage()
    {
        $user = Auth::user();
        $empresaId = $user->empresa_id;
        $accountIds = WhatsappAccount::where('empresa_id', $empresaId)->pluck('id');

        // Stats
        $totalContatos = Contact::whereIn('account_id', $accountIds)->count();
        $semNome = Contact::whereIn('account_id', $accountIds)
            ->where(function ($q) {
                $q->whereNull('name')
                    ->orWhere('name', '')
                    ->orWhere('name', 'Sem nome');
            })
            ->count();

        // Chats individuais sem contato associado
        $chatsSemContato = Chat::whereIn('account_id', $accountIds)
            ->where('chat_type', 'individual')
            ->whereNotExists(function ($q) {
                $q->select('id')
                    ->from('contacts')
                    ->whereColumn('contacts.jid', 'chats.chat_id')
                    ->whereColumn('contacts.account_id', 'chats.account_id');
            })
            ->count();

        $stats = [
            'total_contatos' => $totalContatos,
            'sem_nome' => $semNome,
            'chats_sem_contato' => $chatsSemContato,
        ];

        // Instâncias
        $instancias = WhatsappAccount::where('empresa_id', $empresaId)
            ->orderBy('session_name')
            ->get();

        return view('admin.contacts.sincronizar', compact('stats', 'instancias'));
    }

    public function sincronizar(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:whatsapp_accounts,id',
        ]);

        try {
            $account = WhatsappAccount::findOrFail($validated['account_id']);
            $service = app(EvolutionApiService::class);

            $response = $service->fetchContacts($account->session_name);

            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Erro ao buscar contatos da API');
            }

            $contacts = $response['data'] ?? [];
            $count = 0;

            foreach ($contacts as $contact) {
                $jid = $contact['id'] ?? $contact['remoteJid'] ?? null;
                if (!$jid) {
                    continue;
                }

                // Ignorar grupos (@g.us) e status (@broadcast)
                if (str_contains($jid, '@g.us') || str_contains($jid, '@broadcast')) {
                    continue;
                }

                // Extrair número do jid (remover @s.whatsapp.net ou @lid)
                $phoneNumber = preg_replace('/@.*$/', '', $jid);

                // Limitar tamanho do phone_number (máx 50 caracteres)
                if (strlen($phoneNumber) > 50) {
                    $phoneNumber = substr($phoneNumber, 0, 50);
                }

                Contact::updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'jid' => $jid,
                    ],
                    [
                        'name' => $contact['pushName'] ?? $contact['name'] ?? 'Sem nome',
                        'phone_number' => $phoneNumber,
                        'profile_picture_url' => $contact['profilePictureUrl'] ?? null,
                    ]
                );
                $count++;
            }

            return redirect()->route('admin.contatos.sincronizar.page')
                ->with('success', "Sincronizados {$count} contatos!");
        } catch (\Exception $e) {
            return redirect()->route('admin.contatos.sincronizar.page')
                ->with('error', 'Erro ao sincronizar: ' . $e->getMessage());
        }
    }

    public function enviarMensagem(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'account_id' => 'required|exists:whatsapp_accounts,id',
            'mensagem' => 'required|string|max:4096',
        ]);

        try {
            $account = WhatsappAccount::findOrFail($validated['account_id']);
            $service = app(EvolutionApiService::class);

            $phone = $validated['phone'];

            // Se parece ser um LID, buscar o JID real do contato
            if (str_contains($phone, '@lid') || is_numeric($phone) && strlen($phone) > 15) {
                // Buscar contato pelo phone_number ou jid
                $contact = Contact::where('account_id', $account->id)
                    ->where(function ($q) use ($phone) {
                        $q->where('phone_number', $phone)
                            ->orWhere('jid', $phone)
                            ->orWhere('jid', $phone . '@s.whatsapp.net')
                            ->orWhere('jid', $phone . '@lid');
                    })
                    ->first();

                if ($contact && str_contains($contact->jid, '@s.whatsapp.net')) {
                    $phone = preg_replace('/@.*$/', '', $contact->jid);
                }
            }

            // Remover @s.whatsapp.net ou @lid se houver
            $phone = preg_replace('/@.*$/', '', $phone);

            $result = $service->sendText(
                $account->session_name,
                $phone,
                $validated['mensagem']
            );

            return response()->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Criar contato a partir de um chat existente
     */
    public function criarDoChat(Request $request)
    {
        $validated = $request->validate([
            'chat_id' => 'required|exists:chats,id',
        ]);

        $chat = Chat::findOrFail($validated['chat_id']);

        // Verificar se pertence à empresa do usuário
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id')->toArray();

        if (!in_array($chat->account_id, $accountIds)) {
            return back()->with('error', 'Acesso negado');
        }

        // Extrair número do JID
        $phoneNumber = preg_replace('/@.*$/', '', $chat->chat_id);
        if (strlen($phoneNumber) > 50) {
            $phoneNumber = substr($phoneNumber, 0, 50);
        }

        Contact::updateOrCreate(
            [
                'account_id' => $chat->account_id,
                'jid' => $chat->chat_id,
            ],
            [
                'name' => $chat->chat_name ?: 'Sem nome',
                'phone_number' => $phoneNumber,
            ]
        );

        return back()->with('success', "Contato criado para '{$chat->chat_name}'!");
    }

    /**
     * Listar chats individuais que não possuem contato associado
     */
    public function chatsSemContato()
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $chats = Chat::whereIn('account_id', $accountIds)
            ->where('chat_type', 'individual')
            ->whereNotExists(function ($q) {
                $q->select('id')
                    ->from('contacts')
                    ->whereColumn('contacts.jid', 'chats.chat_id')
                    ->whereColumn('contacts.account_id', 'chats.account_id');
            })
            ->with('account')
            ->withCount('messages')
            ->orderByDesc('last_message_timestamp')
            ->paginate(30);

        $accounts = WhatsappAccount::whereIn('id', $accountIds)->pluck('session_name', 'id');

        return view('admin.contacts.chats-sem-contato', compact('chats', 'accounts'));
    }

    /**
     * Listar chats e contatos duplicados (possíveis mesmo contato com JIDs diferentes)
     */
    public function duplicados()
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        // 1. Buscar CHATS duplicados (mesmo nome)
        $duplicadosChats = Chat::whereIn('account_id', $accountIds)
            ->where('chat_type', 'individual')
            ->whereNotNull('chat_name')
            ->where('chat_name', '!=', '')
            ->select('chat_name', 'account_id')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('GROUP_CONCAT(id ORDER BY last_message_timestamp DESC) as chat_ids')
            ->groupBy('chat_name', 'account_id')
            ->having('count', '>', 1)
            ->get()
            ->map(function ($item) {
                $chatIds = explode(',', $item->chat_ids);
                $chats = Chat::whereIn('id', $chatIds)
                    ->withCount('messages')
                    ->orderByDesc('last_message_timestamp')
                    ->get();

                return [
                    'type' => 'chat',
                    'name' => $item->chat_name,
                    'account_id' => $item->account_id,
                    'chats' => $chats,
                    'contacts' => collect(),
                ];
            });

        // 2. Buscar CONTATOS duplicados (mesmo nome, JIDs diferentes)
        $duplicadosContacts = Contact::whereIn('account_id', $accountIds)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->select('name', 'account_id')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('GROUP_CONCAT(id ORDER BY id DESC) as contact_ids')
            ->groupBy('name', 'account_id')
            ->having('count', '>', 1)
            ->get()
            ->map(function ($item) {
                $contactIds = explode(',', $item->contact_ids);
                $contacts = Contact::whereIn('id', $contactIds)->get();

                // Verificar se já existe como duplicado de chat
                return [
                    'type' => 'contact',
                    'name' => $item->name,
                    'account_id' => $item->account_id,
                    'chats' => collect(),
                    'contacts' => $contacts,
                ];
            });

        // Combinar e remover duplicados que já aparecem em chats
        $chatNames = $duplicadosChats->pluck('name')->toArray();
        $duplicadosContacts = $duplicadosContacts->filter(function ($item) use ($chatNames) {
            return !in_array($item['name'], $chatNames);
        });

        // Usar collect() para garantir que é uma Collection simples (não Eloquent)
        $duplicados = collect()
            ->concat($duplicadosChats->values())
            ->concat($duplicadosContacts->values());

        $accounts = WhatsappAccount::whereIn('id', $accountIds)->pluck('session_name', 'id');

        return view('admin.contacts.duplicados', compact('duplicados', 'accounts'));
    }

    /**
     * Mesclar contatos duplicados (cria alias e deleta o secundário)
     */
    public function mesclarContatos(Request $request)
    {
        $validated = $request->validate([
            'primary_contact_id' => 'required|exists:contacts,id',
            'secondary_contact_id' => 'required|exists:contacts,id|different:primary_contact_id',
        ]);

        $primaryContact = Contact::findOrFail($validated['primary_contact_id']);
        $secondaryContact = Contact::findOrFail($validated['secondary_contact_id']);

        // Verificar se pertencem à mesma empresa
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id')->toArray();

        if (!in_array($primaryContact->account_id, $accountIds) || !in_array($secondaryContact->account_id, $accountIds)) {
            return back()->with('error', 'Acesso negado');
        }

        try {
            // Buscar ou criar chat para o contato principal
            $primaryChat = Chat::where('account_id', $primaryContact->account_id)
                ->where('chat_id', $primaryContact->jid)
                ->first();

            if ($primaryChat) {
                // Criar alias do JID secundário apontando para o chat principal
                ContactAlias::updateOrCreate(
                    [
                        'account_id' => $secondaryContact->account_id,
                        'alias_jid' => $secondaryContact->jid,
                    ],
                    [
                        'primary_chat_id' => $primaryChat->id,
                    ]
                );
            }

            // Deletar o contato secundário
            $secondaryContact->delete();

            return back()->with('success', "Contato mesclado! {$secondaryContact->jid} agora é alias de {$primaryContact->jid}");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao mesclar: ' . $e->getMessage());
        }
    }

    /**
     * Mesclar dois chats em um (move mensagens e conversas)
     */
    public function mesclarChats(Request $request)
    {
        $validated = $request->validate([
            'primary_chat_id' => 'required|exists:chats,id',
            'secondary_chat_id' => 'required|exists:chats,id|different:primary_chat_id',
        ]);

        $primaryChat = Chat::findOrFail($validated['primary_chat_id']);
        $secondaryChat = Chat::findOrFail($validated['secondary_chat_id']);

        // Verificar se pertencem à mesma empresa
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id')->toArray();

        if (!in_array($primaryChat->account_id, $accountIds) || !in_array($secondaryChat->account_id, $accountIds)) {
            return back()->with('error', 'Acesso negado');
        }

        $mergeService = app(ChatMergeService::class);

        if ($mergeService->mergeChats($primaryChat, $secondaryChat)) {
            $message = "Chats mesclados! As mensagens de '{$secondaryChat->chat_id}' foram movidas para '{$primaryChat->chat_id}'";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return back()->with('success', $message);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['error' => 'Erro ao mesclar chats'], 500);
        }

        return back()->with('error', 'Erro ao mesclar chats');
    }

    /**
     * Atualizar nome e número do chat/contato
     */
    public function atualizarChat(Request $request)
    {
        $validated = $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'nome' => 'required|string|max:255',
            'numero' => 'nullable|string|max:50',
        ]);

        $chat = Chat::findOrFail($validated['chat_id']);

        // Verificar se pertence à empresa do usuário
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id')->toArray();

        if (!in_array($chat->account_id, $accountIds)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $oldJid = $chat->chat_id;
        $newJid = $validated['numero'] ? $validated['numero'] . '@s.whatsapp.net' : null;

        // Atualizar chat
        $chat->update(['chat_name' => $validated['nome']]);

        // Se mudou o número, atualizar JID e criar alias
        if ($newJid && $newJid !== $oldJid) {
            // Verificar se já existe um chat com o novo JID
            $existingChat = Chat::where('account_id', $chat->account_id)
                ->where('chat_id', $newJid)
                ->where('id', '!=', $chat->id)
                ->first();

            if ($existingChat) {
                // Mesclar com o chat existente
                $mergeService = app(ChatMergeService::class);
                $mergeService->mergeChats($existingChat, $chat);

                return response()->json([
                    'success' => true,
                    'message' => "Chat mesclado com o número {$validated['numero']}!",
                    'merged' => true,
                ]);
            }

            // Atualizar o JID
            $chat->update(['chat_id' => $newJid]);

            // Criar alias para o JID antigo
            ContactAlias::updateOrCreate(
                ['account_id' => $chat->account_id, 'alias_jid' => $oldJid],
                ['primary_chat_id' => $chat->id]
            );
        }

        // Atualizar ou criar contato
        Contact::updateOrCreate(
            ['account_id' => $chat->account_id, 'jid' => $chat->chat_id],
            [
                'name' => $validated['nome'],
                'phone_number' => $validated['numero'] ?? preg_replace('/@.*$/', '', $chat->chat_id),
            ]
        );

        // Atualizar conversas associadas ao chat
        Conversa::where('chat_id', $chat->id)->update([
            'cliente_nome' => $validated['nome'],
            'cliente_numero' => $validated['numero'] ?? preg_replace('/@.*$/', '', $chat->chat_id),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contato atualizado!',
        ]);
    }

    /**
     * Buscar chats para merge
     */
    public function buscarChats(Request $request)
    {
        $termo = $request->input('termo', '');

        if (strlen($termo) < 2) {
            return response()->json(['chats' => []]);
        }

        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $chats = Chat::whereIn('account_id', $accountIds)
            ->where('chat_type', 'individual')
            ->where(function ($q) use ($termo) {
                $q->where('chat_name', 'like', "%{$termo}%")
                    ->orWhere('chat_id', 'like', "%{$termo}%");
            })
            ->orderByDesc('last_message_timestamp')
            ->take(10)
            ->get(['id', 'chat_id', 'chat_name']);

        return response()->json(['chats' => $chats]);
    }

    /**
     * Abrir conversa com o contato no Dashboard de Atendimento
     */
    public function abrirConversa(Contact $contact)
    {
        $user = Auth::user();

        // Verificar se o contato pertence à empresa do usuário
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id')->toArray();
        if (!in_array($contact->account_id, $accountIds)) {
            return redirect()->route('admin.contatos.index')
                ->with('error', 'Acesso negado');
        }

        // Buscar ou criar o chat
        $chat = Chat::firstOrCreate(
            [
                'account_id' => $contact->account_id,
                'chat_id' => $contact->jid,
            ],
            [
                'chat_name' => $contact->name ?: 'Sem nome',
                'chat_type' => 'individual',
                'last_message_timestamp' => now()->timestamp,
            ]
        );

        // Buscar conversa existente em atendimento ou aguardando
        $conversa = Conversa::where('chat_id', $chat->id)
            ->whereIn('status', ['em_atendimento', 'aguardando'])
            ->first();

        // Se não existe, criar nova conversa
        if (!$conversa) {
            $conversa = Conversa::create([
                'account_id' => $contact->account_id,
                'chat_id' => $chat->id,
                'cliente_numero' => $contact->phone ?: preg_replace('/@.*$/', '', $contact->jid),
                'cliente_nome' => $contact->name ?: 'Sem nome',
                'status' => 'em_atendimento',
                'atendente_id' => $user->id,
                'atendida_em' => now(),
                'ultima_msg_em' => now(),
            ]);

            // Incrementar contador de conversas ativas do usuário
            $user->increment('conversas_ativas');
        } else {
            // Se existe e está aguardando, atribuir ao usuário atual
            if ($conversa->status === 'aguardando') {
                $conversa->update([
                    'atendente_id' => $user->id,
                    'status' => 'em_atendimento',
                    'atendida_em' => now(),
                ]);
                $user->increment('conversas_ativas');
            }
        }

        // Redirecionar para o Dashboard com a conversa aberta
        return redirect()->route('admin.painel', ['conversa' => $conversa->id]);
    }
}
