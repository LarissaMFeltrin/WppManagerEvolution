<?php

namespace App\Services;

use App\DTOs\SyncResult;
use App\Models\Chat;
use App\Models\ContactAlias;
use App\Models\Conversa;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Log;

class HistorySyncService
{
    public function __construct(
        protected EvolutionApiService $evolution,
        protected BaileysService $baileys,
    ) {}

    /**
     * Sincroniza histórico com fallback automático:
     * 1. Evolution API (rápido, limitado)
     * 2. Baileys (completo, requer conexão)
     * 3. Importação manual (sempre funciona)
     */
    public function syncHistory(
        Conversa $conversa,
        int $limit = 100,
        ?string $numeroReal = null
    ): SyncResult {
        $chat = $conversa->chat;
        $account = $conversa->account;

        if (!$chat || !$account) {
            return SyncResult::failed('system', 'Chat ou conta não encontrados');
        }

        // Montar lista de JIDs aceitos
        $acceptedJids = $this->buildAcceptedJids($conversa, $numeroReal);

        if (empty($acceptedJids)) {
            return SyncResult::failed('system', 'Nenhum JID disponível para sincronização');
        }

        $primaryJid = $acceptedJids[0];
        $finalResult = new SyncResult();

        // Tentar Evolution API com retry e busca ampliada
        $maxRetries = 3;
        $totalImported = 0;
        $totalSkipped = 0;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            // Aumentar limite progressivamente
            $currentLimit = min($limit * $attempt, 500);

            Log::info("HistorySync: Tentativa {$attempt}/{$maxRetries} via Evolution API", [
                'conversa' => $conversa->id,
                'jid' => $primaryJid,
                'limit' => $currentLimit,
            ]);

            $evolutionResult = $this->tryEvolutionSync($conversa, $acceptedJids, $currentLimit);

            if ($evolutionResult->isSuccess()) {
                $totalImported += $evolutionResult->imported;
                $totalSkipped += $evolutionResult->skipped;

                $finalResult->addAttempt('evolution', 'success',
                    "Tentativa {$attempt}: {$evolutionResult->imported} importadas, {$evolutionResult->skipped} já existiam"
                );

                // Parar se: importou algo, ou tudo já existia (não tem mais o que buscar)
                if ($totalImported > 0 || $evolutionResult->skipped > 0) {
                    break;
                }
            } else {
                $finalResult->addAttempt('evolution', 'failed',
                    "Tentativa {$attempt}: " . ($evolutionResult->message ?? 'Nenhuma mensagem encontrada')
                );

                // Se já importou algo antes, não precisa mais retry
                if ($totalImported > 0) {
                    break;
                }

                // Delay antes de retry
                if ($attempt < $maxRetries) {
                    usleep(500000); // 0.5s
                }
            }
        }

        if ($totalImported > 0) {
            $result = SyncResult::success('evolution', $totalImported, $totalSkipped);
            $result->attempts = $finalResult->attempts;

            if ($totalImported < 20) {
                $result->message .= ' - Para histórico completo, use Importar Historico.';
            }

            return $result;
        }

        // Se não conseguiu nada, tentar forçar sync reiniciando a instância
        Log::info('HistorySync: Tentando forçar sync via restart da instância');
        try {
            // Garantir que syncFullHistory está ativo
            $this->evolution->updateInstanceSettings($account->session_name, [
                'syncFullHistory' => true,
            ]);

            // Reiniciar instância para forçar resync
            $this->evolution->restartInstance($account->session_name);
            $finalResult->addAttempt('evolution', 'trying', 'Reiniciando instância para sincronizar histórico...');

            // Aguardar reconexão e sync (máx 15s)
            sleep(5);

            // Tentar buscar novamente após restart
            $retryResult = $this->tryEvolutionSync($conversa, $acceptedJids, $limit * 2);
            if ($retryResult->isSuccess() && ($retryResult->imported > 0 || $retryResult->skipped > 0)) {
                $finalResult->addAttempt('evolution', 'success',
                    "Após restart: {$retryResult->imported} importadas, {$retryResult->skipped} já existiam"
                );
                $retryResult->attempts = $finalResult->attempts;
                return $retryResult;
            }
        } catch (\Exception $e) {
            Log::warning('HistorySync: Erro ao forçar sync', ['error' => $e->getMessage()]);
            $finalResult->addAttempt('evolution', 'failed', 'Erro ao reiniciar: ' . $e->getMessage());
        }

        // Se nada funcionou, orientar importação manual
        $importResult = SyncResult::needsManualImport($chat, 0, 0);
        $importResult->attempts = $finalResult->attempts;
        $importResult->addAttempt('manual', 'required', 'Use Importar Historico para carregar mensagens anteriores');
        return $importResult;
    }

    /**
     * Tentar sincronização via Evolution API
     */
    protected function tryEvolutionSync(
        Conversa $conversa,
        array $acceptedJids,
        int $limit
    ): SyncResult {
        try {
            $messages = [];
            $primaryJid = $acceptedJids[0];

            // Buscar do JID principal
            $result = $this->evolution->fetchMessages(
                $conversa->account->session_name,
                $primaryJid,
                $limit
            );

            if ($result['success'] && !empty($result['data'])) {
                $messages = array_merge($messages, $result['data']);
            }

            // Buscar de aliases (LID)
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

            if (empty($messages)) {
                return SyncResult::failed('evolution', 'Nenhuma mensagem encontrada');
            }

            // Processar mensagens
            $imported = 0;
            $skippedNoKey = 0;
            $skippedWrongJid = 0;
            $skippedDuplicate = 0;

            Log::info('HistorySync: Processando mensagens', [
                'total' => count($messages),
                'acceptedJids' => $acceptedJids,
                'chat_id' => $conversa->chat->id,
            ]);

            foreach ($messages as $index => $messageData) {
                if ($imported >= $limit) break;

                $key = $messageData['key'] ?? [];
                $messageId = $key['id'] ?? null;
                $remoteJid = $key['remoteJid'] ?? null;

                if (!$messageId) {
                    $skippedNoKey++;
                    continue;
                }

                // Validar se pertence a este chat
                if (!in_array($remoteJid, $acceptedJids)) {
                    $skippedWrongJid++;
                    if ($index < 5) { // Log apenas os primeiros 5
                        Log::debug('HistorySync: JID não aceito', [
                            'remoteJid' => $remoteJid,
                            'acceptedJids' => $acceptedJids,
                        ]);
                    }
                    continue;
                }

                // Verificar duplicata por message_key exato (coluna UNIQUE)
                if (Message::where('message_key', $messageId)->exists()) {
                    $skippedDuplicate++;
                    continue;
                }

                // Processar e salvar
                $this->processMessage($conversa, $messageData);
                $imported++;
            }

            $totalSkipped = $skippedNoKey + $skippedWrongJid + $skippedDuplicate;

            Log::info('HistorySync: Resultado detalhado', [
                'imported' => $imported,
                'skipped_no_key' => $skippedNoKey,
                'skipped_wrong_jid' => $skippedWrongJid,
                'skipped_duplicate' => $skippedDuplicate,
            ]);

            return SyncResult::success('evolution', $imported, $totalSkipped);

        } catch (\Exception $e) {
            Log::error('HistorySync: Evolution falhou', ['error' => $e->getMessage()]);
            return SyncResult::failed('evolution', $e->getMessage());
        }
    }

    /**
     * Tentar sincronização via Baileys
     */
    protected function tryBaileysSync(Chat $chat, string $jid, int $limit): SyncResult
    {
        try {
            // Iniciar carregamento de histórico
            $loadResult = $this->baileys->loadHistory($jid);

            if (!$loadResult['success']) {
                return SyncResult::failed('baileys', $loadResult['error'] ?? 'Falha ao iniciar carregamento');
            }

            // Aguardar processamento (máx 30s)
            $maxWait = 30;
            $waited = 0;

            while ($waited < $maxWait) {
                sleep(2);
                $waited += 2;

                $status = $this->baileys->loadHistoryStatus($jid);
                $currentStatus = $status['data']['status'] ?? 'unknown';

                if ($currentStatus === 'done' || $currentStatus === 'done_target_reached') {
                    break;
                }

                if ($currentStatus === 'error') {
                    return SyncResult::failed('baileys', 'Erro durante carregamento');
                }
            }

            // Contar mensagens importadas pelo Baileys
            $countAfter = Message::where('chat_id', $chat->id)->count();

            return SyncResult::success('baileys', $countAfter, 0);

        } catch (\Exception $e) {
            Log::error('HistorySync: Baileys falhou', ['error' => $e->getMessage()]);
            return SyncResult::failed('baileys', $e->getMessage());
        }
    }

    /**
     * Verificar se Baileys está disponível
     */
    protected function isBaileysAvailable(): bool
    {
        try {
            $health = $this->baileys->health();
            return $health['success'] &&
                   in_array($health['data']['status'] ?? '', ['connected', 'open']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Montar lista de JIDs aceitos para sincronização
     */
    protected function buildAcceptedJids(Conversa $conversa, ?string $numeroReal = null): array
    {
        $acceptedJids = [];
        $chatJid = $conversa->chat->chat_id ?? null;
        $clienteNumero = $conversa->cliente_numero;

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

        if ($numeroReal) {
            $numeroLimpo = preg_replace('/\D/', '', $numeroReal);
            $acceptedJids[] = $numeroLimpo . '@s.whatsapp.net';
        }

        // Buscar aliases vinculados (LID <-> número)
        if ($conversa->chat) {
            $aliases = ContactAlias::where('primary_chat_id', $conversa->chat->id)
                ->pluck('alias_jid')
                ->toArray();
            $acceptedJids = array_merge($acceptedJids, $aliases);
        }

        return array_unique($acceptedJids);
    }

    /**
     * Processar e salvar mensagem individual
     */
    protected function processMessage(Conversa $conversa, array $messageData): void
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

        $senderJid = $fromMe
            ? $conversa->account->owner_jid
            : ($isGroup ? $participant : $remoteJid);

        Message::create([
            'chat_id' => $conversa->chat->id,
            'message_key' => $messageId,
            'from_jid' => $senderJid ?? $remoteJid,
            'sender_name' => $fromMe ? null : $senderName,
            'participant_jid' => $isGroup ? $participant : null,
            'to_jid' => $fromMe ? $remoteJid : $conversa->account->owner_jid,
            'message_text' => $messageText,
            'message_type' => $messageType,
            'media_mime_type' => $mediaMimeType,
            'media_filename' => $mediaFilename,
            'is_from_me' => $fromMe,
            'timestamp' => $messageData['messageTimestamp'] ?? time(),
            'status' => 'delivered',
            'message_raw' => $messageData,
        ]);
    }
}
