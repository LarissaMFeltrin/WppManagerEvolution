<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.evolution.url', 'http://localhost:8080'), '/');
        $this->apiKey = config('services.evolution.api_key', '');
    }

    protected function request(string $method, string $endpoint, array $data = [], int $timeout = 15): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $ch = curl_init();

            if (strtolower($method) === 'get') {
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                }
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $this->apiKey,
                    'Content-Type: application/json',
                    'Connection: close',
                ],
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
            ]);

            if (strtolower($method) === 'put') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            } elseif (strtolower($method) === 'delete') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }

            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error('Evolution API cURL Error', ['url' => $url, 'error' => $error]);
                return ['success' => false, 'error' => $error];
            }

            $json = json_decode($body, true) ?? [];

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'data' => $json];
            }

            $errorMsg = $json['message'] ?? $json['error'] ?? $json['response']['message'] ?? $body;

            Log::warning('Evolution API Error Response', [
                'url' => $url,
                'status' => $httpCode,
                'body' => $json,
            ]);

            return [
                'success' => false,
                'error' => $errorMsg,
                'status' => $httpCode,
                'body' => $json,
            ];
        } catch (\Exception $e) {
            Log::error('Evolution API Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // === Instâncias ===

    public function createInstance(string $instanceName, array $options = []): array
    {
        // Evolution API v2.x - formato atualizado
        return $this->request('post', '/instance/create', array_merge([
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
        ], $options));
    }

    public function deleteInstance(string $instanceName): array
    {
        return $this->request('delete', "/instance/delete/{$instanceName}");
    }

    public function getInstanceInfo(string $instanceName): array
    {
        return $this->request('get', "/instance/fetchInstances", [
            'instanceName' => $instanceName,
        ]);
    }

    public function getConnectionState(string $instanceName): array
    {
        return $this->request('get', "/instance/connectionState/{$instanceName}");
    }

    public function connectInstance(string $instanceName): array
    {
        return $this->request('get', "/instance/connect/{$instanceName}");
    }

    public function disconnectInstance(string $instanceName): array
    {
        return $this->request('delete', "/instance/logout/{$instanceName}");
    }

    public function restartInstance(string $instanceName): array
    {
        return $this->request('post', "/instance/restart/{$instanceName}");
    }

    public function updateInstanceSettings(string $instanceName, array $settings): array
    {
        // Evolution API v2.x - formato atualizado
        $defaults = [
            'rejectCall' => false,
            'groupsIgnore' => false,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => false,
            'syncFullHistory' => false,
        ];

        return $this->request('post', "/settings/set/{$instanceName}", array_merge($defaults, $settings));
    }

    public function getInstanceSettings(string $instanceName): array
    {
        return $this->request('get', "/settings/find/{$instanceName}");
    }

    // === QR Code e Pairing Code ===

    public function getQrCode(string $instanceName): array
    {
        return $this->request('get', "/instance/connect/{$instanceName}");
    }

    /**
     * Conectar instância via Pairing Code (número de telefone)
     * Útil para reconectar sem precisar escanear QR code
     */
    public function connectWithPairingCode(string $instanceName, string $phoneNumber): array
    {
        // Limpar número (remover caracteres não numéricos)
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

        return $this->request('post', "/instance/connect/{$instanceName}", [
            'number' => $phoneNumber,
        ]);
    }

    // === Mensagens ===

    public function sendText(string $instanceName, string $number, string $text, ?string $quotedMessageId = null, ?string $remoteJid = null, bool $quotedFromMe = false): array
    {
        // Evolution API v2.3.x - formato simplificado
        $payload = [
            'number' => $number,
            'text' => $text,
        ];

        // Se tem mensagem citada, adicionar no payload
        if ($quotedMessageId) {
            $payload['quoted'] = [
                'key' => [
                    'remoteJid' => $remoteJid ?? ($number . '@s.whatsapp.net'),
                    'fromMe' => $quotedFromMe,
                    'id' => $quotedMessageId,
                ],
            ];
        }

        return $this->request('post', "/message/sendText/{$instanceName}", $payload, 30);
    }

    public function sendMedia(string $instanceName, string $number, string $mediaType, string $mediaUrl, ?string $caption = null): array
    {
        // Evolution API v2.3.x - formato simplificado
        $payload = [
            'number' => $number,
            'mediatype' => $mediaType,
            'media' => $mediaUrl,
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        return $this->request('post', "/message/sendMedia/{$instanceName}", $payload, 30);
    }

    /**
     * Enviar mídia usando base64 (Evolution API v2.3.x)
     */
    public function sendMediaBase64(string $instanceName, string $number, string $mediaType, string $base64, string $mimeType, ?string $caption = null, ?string $fileName = null): array
    {
        // Evolution API v2.3.x - formato simplificado
        $payload = [
            'number' => $number,
            'mediatype' => $mediaType,
            'mimetype' => $mimeType,
            'media' => $base64,
        ];

        if ($caption !== null && trim($caption) !== '') {
            $payload['caption'] = (string) $caption;
        }

        if ($fileName) {
            $payload['fileName'] = $fileName;
        }

        return $this->request('post', "/message/sendMedia/{$instanceName}", $payload, 60);
    }

    public function sendImageBase64(string $instanceName, string $number, string $base64, string $mimeType, ?string $caption = null): array
    {
        return $this->sendMediaBase64($instanceName, $number, 'image', $base64, $mimeType, $caption);
    }

    public function sendVideoBase64(string $instanceName, string $number, string $base64, string $mimeType, ?string $caption = null): array
    {
        return $this->sendMediaBase64($instanceName, $number, 'video', $base64, $mimeType, $caption);
    }

    public function sendDocumentBase64(string $instanceName, string $number, string $base64, string $mimeType, string $fileName): array
    {
        return $this->sendMediaBase64($instanceName, $number, 'document', $base64, $mimeType, null, $fileName);
    }

    public function sendAudioBase64(string $instanceName, string $number, string $base64, string $mimeType): array
    {
        // Evolution API v2.3.x - formato para áudio em base64
        return $this->request('post', "/message/sendWhatsAppAudio/{$instanceName}", [
            'number' => $number,
            'audio' => $base64,
            'encoding' => true,
        ], 60);
    }

    public function sendImage(string $instanceName, string $number, string $imageUrl, ?string $caption = null): array
    {
        return $this->sendMedia($instanceName, $number, 'image', $imageUrl, $caption);
    }

    public function sendVideo(string $instanceName, string $number, string $videoUrl, ?string $caption = null): array
    {
        return $this->sendMedia($instanceName, $number, 'video', $videoUrl, $caption);
    }

    public function sendAudio(string $instanceName, string $number, string $audioUrl): array
    {
        // Evolution API v2.3.x - formato simplificado
        return $this->request('post', "/message/sendWhatsAppAudio/{$instanceName}", [
            'number' => $number,
            'audio' => $audioUrl,
        ], 30);
    }

    public function sendDocument(string $instanceName, string $number, string $documentUrl, string $fileName): array
    {
        // Evolution API v2.3.x - formato simplificado
        return $this->request('post', "/message/sendMedia/{$instanceName}", [
            'number' => $number,
            'mediatype' => 'document',
            'media' => $documentUrl,
            'fileName' => $fileName,
        ], 60);
    }

    public function sendLocation(string $instanceName, string $number, float $latitude, float $longitude, ?string $name = null): array
    {
        return $this->request('post', "/message/sendLocation/{$instanceName}", [
            'number' => $number,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
        ]);
    }

    public function sendReaction(string $instanceName, string $remoteJid, string $messageId, string $emoji, bool $fromMe = false, ?string $participant = null): array
    {
        $key = [
            'remoteJid' => $remoteJid,
            'id' => $messageId,
            'fromMe' => $fromMe,
        ];

        // Para grupos, incluir participant
        if ($participant) {
            $key['participant'] = $participant;
        }

        // Evolution API v2.3.x - formato simplificado (sem reactionMessage aninhado)
        return $this->request('post', "/message/sendReaction/{$instanceName}", [
            'key' => $key,
            'reaction' => $emoji,
        ], timeout: 30);
    }

    public function editMessage(string $instanceName, string $remoteJid, string $messageId, string $newText): array
    {
        return $this->request('post', "/message/updateMessage/{$instanceName}", [
            'key' => [
                'remoteJid' => $remoteJid,
                'id' => $messageId,
                'fromMe' => true,
            ],
            'message' => [
                'conversation' => $newText,
            ],
        ]);
    }

    public function deleteMessage(string $instanceName, string $remoteJid, string $messageId, bool $fromMe = true): array
    {
        return $this->request('delete', "/chat/deleteMessageForEveryone/{$instanceName}", [
            'key' => [
                'remoteJid' => $remoteJid,
                'id' => $messageId,
                'fromMe' => $fromMe,
            ],
        ]);
    }

    public function sendPresence(string $instanceName, string $number, string $presence = 'composing'): array
    {
        // presence: composing, recording, paused - Formato Evolution API v2.3.x
        // Timeout curto (3s) para não bloquear outras requisições
        return $this->request('post', "/chat/sendPresence/{$instanceName}", [
            'number' => $number,
            'presence' => $presence,
            'delay' => 1000,
        ], 3);
    }

    // === Histórico ===

    public function fetchMessages(string $instanceName, string $remoteJid = '', int $limit = 20): array
    {
        $allMessages = [];
        $page = 1;
        $maxPages = 20; // Segurança para não loopear infinito
        $perPage = min($limit, 100);

        do {
            $payload = ['limit' => $perPage, 'page' => $page];

            if ($remoteJid) {
                $payload['where'] = [
                    'key' => [
                        'remoteJid' => $remoteJid,
                    ],
                ];
            }

            $result = $this->request('post', "/chat/findMessages/{$instanceName}", $payload);

            if (!$result['success']) {
                // Se já temos mensagens parciais, retornar elas
                if (!empty($allMessages)) {
                    return ['success' => true, 'data' => $allMessages];
                }
                return $result;
            }

            $data = $result['data'];

            // A API retorna {messages: {total, pages, records}} ou array direto
            if (isset($data['messages']['records'])) {
                $records = $data['messages']['records'];
                $totalPages = $data['messages']['pages'] ?? 1;
            } elseif (is_array($data) && !isset($data['messages'])) {
                $records = $data;
                $totalPages = 1;
            } else {
                break;
            }

            $allMessages = array_merge($allMessages, $records);

            if ($page >= $totalPages || $page >= $maxPages || count($allMessages) >= $limit) {
                break;
            }

            $page++;
        } while (true);

        return ['success' => true, 'data' => $allMessages];
    }

    // === Chats ===

    public function fetchChats(string $instanceName): array
    {
        return $this->request('get', "/chat/fetchChats/{$instanceName}");
    }

    public function markAsRead(string $instanceName, string $remoteJid): array
    {
        return $this->request('post', "/chat/markMessageAsRead/{$instanceName}", [
            'readMessages' => [
                ['remoteJid' => $remoteJid],
            ],
        ]);
    }

    public function archiveChat(string $instanceName, string $remoteJid, bool $archive = true): array
    {
        return $this->request('post', "/chat/archiveChat/{$instanceName}", [
            'chat' => $remoteJid,
            'archive' => $archive,
        ]);
    }

    // === Contatos ===

    public function fetchContacts(string $instanceName): array
    {
        return $this->request('post', "/chat/findContacts/{$instanceName}");
    }

    public function getProfilePicture(string $instanceName, string $number): array
    {
        return $this->request('get', "/chat/fetchProfilePictureUrl/{$instanceName}", [
            'number' => $number,
        ]);
    }

    public function checkWhatsappNumber(string $instanceName, array $numbers): array
    {
        return $this->request('post', "/chat/whatsappNumbers/{$instanceName}", [
            'numbers' => $numbers,
        ]);
    }

    // === Webhooks ===

    public function setWebhook(string $instanceName, string $webhookUrl, array $events = [], bool $base64 = true): array
    {
        // Evolution API v2.x - formato com objeto webhook aninhado
        return $this->request('post', "/webhook/set/{$instanceName}", [
            'webhook' => [
                'enabled' => true,
                'url' => $webhookUrl,
                'webhookBase64' => $base64,
                'webhookByEvents' => false,
                'events' => $events ?: [
                    'QRCODE_UPDATED',
                    'MESSAGES_SET',
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                    'MESSAGES_DELETE',
                    'SEND_MESSAGE',
                    'CONNECTION_UPDATE',
                    'PRESENCE_UPDATE',
                ],
            ],
        ]);
    }

    public function getWebhook(string $instanceName): array
    {
        return $this->request('get', "/webhook/find/{$instanceName}");
    }

    // === Grupos ===

    public function fetchGroups(string $instanceName): array
    {
        return $this->request('get', "/group/fetchAllGroups/{$instanceName}", [
            'getParticipants' => 'false',
        ]);
    }

    public function getGroupInfo(string $instanceName, string $groupJid): array
    {
        // Timeout maior (45s) para busca de info de grupo - roda em background via job
        return $this->request('get', "/group/findGroupInfos/{$instanceName}", [
            'groupJid' => $groupJid,
        ], 45);
    }

    public function createGroup(string $instanceName, string $subject, array $participants): array
    {
        return $this->request('post', "/group/create/{$instanceName}", [
            'subject' => $subject,
            'participants' => $participants,
        ]);
    }

    // === Download de Mídia ===

    public function downloadMedia(string $instanceName, string $messageId, ?string $remoteJid = null, bool $fromMe = false): array
    {
        $key = ['id' => $messageId];

        if ($remoteJid) {
            $key['remoteJid'] = $remoteJid;
        }
        $key['fromMe'] = $fromMe;

        return $this->request('post', "/chat/getBase64FromMediaMessage/{$instanceName}", [
            'message' => [
                'key' => $key,
            ],
            'convertToMp4' => false,
        ]);
    }

    // === Status da API ===

    public function healthCheck(): array
    {
        return $this->request('get', '/');
    }

    public function fetchAllInstances(): array
    {
        return $this->request('get', '/instance/fetchInstances');
    }
}
