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
            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->{$method}($url, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $body = $response->json();
            $errorMsg = $body['message']
                ?? $body['error']
                ?? $body['response']['message']
                ?? json_encode($body);

            Log::warning('Evolution API Error Response', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'success' => false,
                'error' => $errorMsg,
                'status' => $response->status(),
                'body' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('Evolution API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // === Instâncias ===

    public function createInstance(string $instanceName, array $options = []): array
    {
        return $this->request('post', '/instance/create', array_merge([
            'instanceName' => $instanceName,
            'qrcode' => true,
            'groups_ignore' => false,  // Receber mensagens de grupos
            'always_online' => false,
            'read_messages' => false,
            'read_status' => false,
            'reject_call' => false,
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
        // v1.7.x requer todos os campos
        $defaults = [
            'reject_call' => false,
            'groups_ignore' => false,
            'always_online' => false,
            'read_messages' => false,
            'read_status' => false,
            'sync_full_history' => false,
        ];

        return $this->request('post', "/settings/set/{$instanceName}", array_merge($defaults, $settings));
    }

    public function getInstanceSettings(string $instanceName): array
    {
        return $this->request('get', "/settings/find/{$instanceName}");
    }

    // === QR Code ===

    public function getQrCode(string $instanceName): array
    {
        return $this->request('get', "/instance/connect/{$instanceName}");
    }

    // === Mensagens ===

    public function sendText(string $instanceName, string $number, string $text, ?string $quotedMessageId = null, ?string $remoteJid = null, bool $quotedFromMe = false): array
    {
        $payload = [
            'number' => $number,
            'textMessage' => [
                'text' => $text,
            ],
        ];

        // Se tem mensagem citada, adicionar no payload
        if ($quotedMessageId) {
            // Formato para Evolution API v1.7.x
            $payload['options'] = [
                'quoted' => [
                    'key' => [
                        'remoteJid' => $remoteJid ?? ($number . '@s.whatsapp.net'),
                        'fromMe' => $quotedFromMe,
                        'id' => $quotedMessageId,
                    ],
                ],
            ];
        }

        return $this->request('post', "/message/sendText/{$instanceName}", $payload);
    }

    public function sendMedia(string $instanceName, string $number, string $mediaType, string $mediaUrl, ?string $caption = null): array
    {
        // Formato Evolution API v2.x
        $mediaMessage = [
            'mediatype' => $mediaType,
            'media' => $mediaUrl,
        ];

        if ($caption) {
            $mediaMessage['caption'] = $caption;
        }

        return $this->request('post', "/message/sendMedia/{$instanceName}", [
            'number' => $number,
            'mediaMessage' => $mediaMessage,
        ]);
    }

    /**
     * Enviar mídia usando base64 (Evolution API v1.7.x)
     */
    public function sendMediaBase64(string $instanceName, string $number, string $mediaType, string $base64, string $mimeType, ?string $caption = null, ?string $fileName = null): array
    {
        // Evolution API v1.7.x - base64 PURO sem prefixo data:mime;base64,
        $mediaMessage = [
            'mediatype' => $mediaType,
            'mimetype' => $mimeType,
            'media' => $base64,
        ];

        // Caption deve ser string válida, não null
        if ($caption !== null && trim($caption) !== '') {
            $mediaMessage['caption'] = (string) $caption;
        }

        if ($fileName) {
            $mediaMessage['fileName'] = $fileName;
        }

        return $this->request('post', "/message/sendMedia/{$instanceName}", [
            'number' => $number,
            'mediaMessage' => $mediaMessage,
        ]);
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
        // Evolution API v1.7.x formato para áudio
        return $this->request('post', "/message/sendWhatsAppAudio/{$instanceName}", [
            'number' => $number,
            'encoding' => true,
            'audio' => $base64,
        ]);
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
        return $this->request('post', "/message/sendWhatsAppAudio/{$instanceName}", [
            'number' => $number,
            'audioMessage' => [
                'audio' => $audioUrl,
            ],
        ]);
    }

    public function sendDocument(string $instanceName, string $number, string $documentUrl, string $fileName): array
    {
        // Formato Evolution API v2.x
        return $this->request('post', "/message/sendMedia/{$instanceName}", [
            'number' => $number,
            'mediaMessage' => [
                'mediatype' => 'document',
                'media' => $documentUrl,
                'fileName' => $fileName,
            ],
        ]);
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

    public function sendReaction(string $instanceName, string $remoteJid, string $messageId, string $emoji, bool $fromMe = false): array
    {
        return $this->request('post', "/message/sendReaction/{$instanceName}", [
            'reactionMessage' => [
                'key' => [
                    'remoteJid' => $remoteJid,
                    'id' => $messageId,
                    'fromMe' => $fromMe,
                ],
                'reaction' => $emoji,
            ],
        ]);
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
        // presence: composing, recording, paused - Formato Evolution API v2.x
        return $this->request('post', "/chat/sendPresence/{$instanceName}", [
            'number' => $number,
            'options' => [
                'presence' => $presence,
                'delay' => 1000,
            ],
        ]);
    }

    // === Histórico ===

    public function fetchMessages(string $instanceName, string $remoteJid = '', int $limit = 20): array
    {
        $payload = ['limit' => $limit];

        if ($remoteJid) {
            $payload['where'] = [
                'key' => [
                    'remoteJid' => $remoteJid,
                ],
            ];
        }

        return $this->request('post', "/chat/findMessages/{$instanceName}", $payload);
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
        return $this->request('post', "/webhook/set/{$instanceName}", [
            'enabled' => true,
            'url' => $webhookUrl,
            'webhookBase64' => $base64, // Envia base64 da mídia diretamente no webhook
            'webhookByEvents' => false,
            'events' => $events ?: [
                'QRCODE_UPDATED',
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'MESSAGES_DELETE',
                'SEND_MESSAGE',
                'CONNECTION_UPDATE',
                'PRESENCE_UPDATE',
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
