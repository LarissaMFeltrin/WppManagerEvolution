<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço complementar para funcionalidades do Baileys
 * que não estão disponíveis na Evolution API.
 *
 * Requer o serviço Node.js rodando em paralelo.
 */
class BaileysService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.baileys.url', 'http://localhost:3001'), '/');
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $response = Http::timeout(30)->{$method}($url, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error'] ?? 'Erro desconhecido',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Baileys Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar status do serviço Baileys
     */
    public function health(): array
    {
        return $this->request('get', '/health');
    }

    /**
     * Reagir a uma mensagem com emoji
     */
    public function reactMessage(string $jid, string $messageId, string $emoji): array
    {
        return $this->request('post', '/api/react-message', [
            'jid' => $jid,
            'messageId' => $messageId,
            'emoji' => $emoji,
        ]);
    }

    /**
     * Deletar mensagem para todos
     */
    public function deleteMessage(string $jid, string $messageId): array
    {
        return $this->request('post', '/api/delete-message', [
            'jid' => $jid,
            'messageId' => $messageId,
        ]);
    }

    /**
     * Editar mensagem enviada
     */
    public function editMessage(string $jid, string $messageId, string $newText): array
    {
        return $this->request('post', '/api/edit-message', [
            'jid' => $jid,
            'messageId' => $messageId,
            'newText' => $newText,
        ]);
    }

    /**
     * Encaminhar mensagem para outro chat
     */
    public function forwardMessage(string $fromJid, string $toJid, string $messageId, ?int $sentByUserId = null): array
    {
        return $this->request('post', '/api/forward-message', [
            'fromJid' => $fromJid,
            'toJid' => $toJid,
            'messageId' => $messageId,
            'sentByUserId' => $sentByUserId,
        ]);
    }

    /**
     * Marcar chat como lido
     */
    public function markAsRead(string $jid): array
    {
        return $this->request('post', "/api/mark-read/{$jid}");
    }

    /**
     * Buscar foto de perfil
     */
    public function getProfilePicture(string $jid): array
    {
        return $this->request('get', "/api/profile-pic/{$jid}");
    }

    /**
     * Sincronizar nomes de grupos
     */
    public function syncGroups(): array
    {
        return $this->request('post', '/api/sync-groups');
    }

    /**
     * Carregar histórico de mensagens
     */
    public function loadHistory(string $jid, ?int $untilTimestamp = null): array
    {
        $query = $untilTimestamp ? "?until={$untilTimestamp}" : '';
        return $this->request('post', "/api/load-history/{$jid}{$query}");
    }

    /**
     * Verificar status do carregamento de histórico
     */
    public function loadHistoryStatus(string $jid): array
    {
        return $this->request('get', "/api/load-history-status/{$jid}");
    }

    /**
     * Buscar mensagens de um chat
     */
    public function getMessages(string $jid, int $limit = 200): array
    {
        return $this->request('get', "/api/messages/{$jid}?limit={$limit}");
    }

    /**
     * Sincronizar mensagens anteriores de um chat
     */
    public function syncChat(string $jid, int $count = 50): array
    {
        return $this->request('post', "/api/sync-chat/{$jid}?count={$count}");
    }

    /**
     * Enviar mensagem de texto (via Baileys)
     */
    public function sendText(string $jid, string $text, ?string $quotedMessageId = null, ?int $sentByUserId = null): array
    {
        return $this->request('post', '/api/send-message', [
            'jid' => $jid,
            'text' => $text,
            'quotedMessageId' => $quotedMessageId,
            'sentByUserId' => $sentByUserId,
        ]);
    }
}
