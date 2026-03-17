<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\ContactAlias;
use App\Models\Conversa;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\EvolutionApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MonitorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $empresaId = $user->empresa_id;
        $accountIds = $user->getAccountIds();

        // Stats para os cards
        $stats = [
            'instancias_online' => WhatsappAccount::where('empresa_id', $empresaId)
                ->where('is_connected', true)
                ->count(),
            'instancias_total' => WhatsappAccount::where('empresa_id', $empresaId)->count(),
            'na_fila' => Conversa::whereIn('account_id', $accountIds)->where('status', 'aguardando')->count(),
            'em_atendimento' => Conversa::whereIn('account_id', $accountIds)->where('status', 'em_atendimento')->count(),
            'mensagens_hoje' => Message::whereHas('chat', fn($q) => $q->whereIn('account_id', $accountIds))
                ->whereDate('created_at', today())
                ->count(),
        ];

        // Instâncias WhatsApp
        $instancias = WhatsappAccount::where('empresa_id', $empresaId)
            ->with('empresa')
            ->orderBy('session_name')
            ->get();

        // Atendentes
        $atendentes = User::where('empresa_id', $empresaId)
            ->where('role', 'agent')
            ->withCount(['conversas as conversas_ativas' => function ($q) {
                $q->where('status', 'em_atendimento');
            }])
            ->orderBy('name')
            ->get();

        // Conversas ativas
        $conversasAtivas = Conversa::whereIn('account_id', $accountIds)
            ->whereIn('status', ['aguardando', 'em_atendimento'])
            ->with(['atendente', 'account', 'chat.messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Atividade recente (últimas mensagens)
        $atividadeRecente = Message::with(['chat.account'])
            ->whereHas('chat', fn($q) => $q->whereIn('account_id', $accountIds))
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        return view('admin.monitor.index', compact(
            'stats',
            'instancias',
            'atendentes',
            'conversasAtivas',
            'atividadeRecente'
        ));
    }

    public function supervisao()
    {
        $user = Auth::user();
        $empresaId = $user->empresa_id;
        $accountIds = $user->getAccountIds();

        // Conversas finalizadas hoje para métricas
        $conversasFinalizadasHoje = Conversa::whereIn('account_id', $accountIds)
            ->where('status', 'finalizada')
            ->whereDate('finalizada_em', today())
            ->whereNotNull('atendida_em')
            ->whereNotNull('finalizada_em')
            ->get();

        // Calcular tempo médio de atendimento (em minutos)
        $tempoMedioAtendimento = 0;
        if ($conversasFinalizadasHoje->count() > 0) {
            $totalMinutos = $conversasFinalizadasHoje->sum(function ($c) {
                return $c->atendida_em->diffInMinutes($c->finalizada_em);
            });
            $tempoMedioAtendimento = round($totalMinutos / $conversasFinalizadasHoje->count());
        }

        // Calcular SLA (% respondidas em menos de 5 minutos)
        $conversasHoje = Conversa::whereIn('account_id', $accountIds)
            ->whereDate('created_at', today())
            ->whereNotNull('atendida_em')
            ->get();

        $dentroSla = $conversasHoje->filter(function ($c) {
            return $c->created_at->diffInMinutes($c->atendida_em) <= 5;
        })->count();

        $slaPercentual = $conversasHoje->count() > 0
            ? round(($dentroSla / $conversasHoje->count()) * 100)
            : 100;

        // Stats gerais
        $stats = [
            'online' => User::where('empresa_id', $empresaId)
                ->where('role', 'agent')
                ->where('status_atendimento', 'online')
                ->count(),
            'atendendo' => Conversa::whereIn('account_id', $accountIds)
                ->where('status', 'em_atendimento')
                ->distinct('atendente_id')
                ->count('atendente_id'),
            'na_fila' => Conversa::whereIn('account_id', $accountIds)
                ->where('status', 'aguardando')
                ->count(),
            'tempo_medio' => $tempoMedioAtendimento,
            'sla' => $slaPercentual,
            'finalizadas_hoje' => $conversasFinalizadasHoje->count(),
            'total_hoje' => $conversasHoje->count(),
        ];

        // Atendentes com métricas detalhadas
        $atendentes = User::where('empresa_id', $empresaId)
            ->where('role', 'agent')
            ->withCount(['conversas as conversas_ativas' => function ($q) {
                $q->where('status', 'em_atendimento');
            }])
            ->withCount(['conversas as finalizadas_hoje' => function ($q) {
                $q->where('status', 'finalizada')->whereDate('finalizada_em', today());
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($atendente) use ($accountIds) {
                // Calcular tempo médio do atendente
                $conversas = Conversa::whereIn('account_id', $accountIds)
                    ->where('atendente_id', $atendente->id)
                    ->where('status', 'finalizada')
                    ->whereDate('finalizada_em', today())
                    ->whereNotNull('atendida_em')
                    ->whereNotNull('finalizada_em')
                    ->get();

                if ($conversas->count() > 0) {
                    $total = $conversas->sum(fn($c) => $c->atendida_em->diffInMinutes($c->finalizada_em));
                    $atendente->tempo_medio = round($total / $conversas->count());
                } else {
                    $atendente->tempo_medio = 0;
                }

                // Taxa de ocupação
                $maxConversas = $atendente->max_conversas_simultaneas ?? 8;
                $atendente->taxa_ocupacao = round(($atendente->conversas_ativas / $maxConversas) * 100);

                return $atendente;
            });

        // Conversas em atendimento com mensagens para preview (excluindo apagadas)
        $conversasEmAtendimento = Conversa::whereIn('account_id', $accountIds)
            ->where('status', 'em_atendimento')
            ->with(['atendente', 'account', 'chat.messages' => function ($q) {
                $q->where('is_deleted', false)->orderBy('timestamp', 'desc')->limit(10);
            }])
            ->orderBy('atendida_em', 'desc')
            ->get();

        return view('admin.monitor.supervisao', compact(
            'stats',
            'atendentes',
            'conversasEmAtendimento'
        ));
    }

    public function historico(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        $empresaId = $user->empresa_id;
        $accountIds = $user->getAccountIds();

        // Stats gerais
        $stats = [
            'total' => Conversa::whereIn('account_id', $accountIds)->count(),
            'finalizadas' => Conversa::whereIn('account_id', $accountIds)->where('status', 'finalizada')->count(),
            'em_atendimento' => Conversa::whereIn('account_id', $accountIds)->where('status', 'em_atendimento')->count(),
            'na_fila' => Conversa::whereIn('account_id', $accountIds)->where('status', 'aguardando')->count(),
        ];

        // Atendentes com estatísticas
        $atendentes = User::where('empresa_id', $empresaId)
            ->where('role', 'agent')
            ->withCount([
                'conversas as em_atendimento' => function ($q) use ($accountIds) {
                    $q->whereIn('account_id', $accountIds)->where('status', 'em_atendimento');
                },
                'conversas as finalizadas' => function ($q) use ($accountIds) {
                    $q->whereIn('account_id', $accountIds)->where('status', 'finalizada');
                },
                'conversas as devolvidas' => function ($q) use ($accountIds) {
                    $q->whereIn('account_id', $accountIds)->whereNotNull('devolvida_por');
                },
            ])
            ->get()
            ->map(function ($atendente) use ($accountIds) {
                // Calcular tempo médio de atendimento
                $tempoMedio = Conversa::whereIn('account_id', $accountIds)
                    ->where('atendente_id', $atendente->id)
                    ->where('status', 'finalizada')
                    ->whereNotNull('atendida_em')
                    ->whereNotNull('finalizada_em')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, atendida_em, finalizada_em)) as tempo_medio')
                    ->value('tempo_medio');

                $atendente->tempo_medio = $tempoMedio ? round($tempoMedio) : 0;
                return $atendente;
            });

        // Query de conversas com filtros
        $query = Conversa::whereIn('account_id', $accountIds)
            ->with(['atendente', 'account']);

        // Filtro de busca
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('cliente_nome', 'like', "%{$search}%")
                    ->orWhere('cliente_numero', 'like', "%{$search}%");
            });
        }

        // Filtro de atendente
        if ($request->filled('atendente_id')) {
            $query->where('atendente_id', $request->atendente_id);
        }

        // Filtro de status
        if ($request->filled('status') && is_array($request->status)) {
            $query->whereIn('status', $request->status);
        }

        // Filtro de período
        if ($request->filled('periodo')) {
            switch ($request->periodo) {
                case 'hoje':
                    $query->whereDate('created_at', today());
                    break;
                case 'semana':
                    $query->where('created_at', '>=', now()->startOfWeek());
                    break;
                case 'mes':
                    $query->where('created_at', '>=', now()->startOfMonth());
                    break;
                case '3meses':
                    $query->where('created_at', '>=', now()->subMonths(3));
                    break;
            }
        }

        $conversas = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('admin.monitor.historico', compact('stats', 'atendentes', 'conversas'));
    }

    /**
     * Página de Saúde do Sistema - Erros, Alertas e Diagnósticos
     */
    public function saude()
    {
        $user = Auth::user();
        $empresaId = $user->empresa_id;

        // Instâncias e seus status na Evolution API
        $instancias = WhatsappAccount::where('empresa_id', $empresaId)->get();
        $evolution = app(EvolutionApiService::class);

        $statusInstancias = [];
        foreach ($instancias as $instancia) {
            try {
                $result = $evolution->getConnectionState($instancia->session_name);
                $state = $result['data']['instance']['state'] ?? $result['data']['state'] ?? 'unknown';
                $statusInstancias[] = [
                    'nome' => $instancia->session_name,
                    'db_connected' => $instancia->is_connected,
                    'api_status' => $state,
                    'ok' => $state === 'open',
                ];
            } catch (\Exception $e) {
                $statusInstancias[] = [
                    'nome' => $instancia->session_name,
                    'db_connected' => $instancia->is_connected,
                    'api_status' => 'error: ' . $e->getMessage(),
                    'ok' => false,
                ];
            }
        }

        // Ler últimos erros do log
        $errosRecentes = [];
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            $logContent = File::get($logPath);
            // Pegar últimas 500KB do arquivo
            if (strlen($logContent) > 500000) {
                $logContent = substr($logContent, -500000);
            }

            // Extrair erros e warnings
            preg_match_all('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|WARNING):\s*([^\n]+)/', $logContent, $matches, PREG_SET_ORDER);

            $errosRecentes = collect($matches)
                ->reverse()
                ->take(30)
                ->map(function ($match) {
                    return [
                        'data' => $match[1],
                        'tipo' => $match[2],
                        'mensagem' => substr($match[3], 0, 200),
                    ];
                })
                ->values()
                ->toArray();
        }

        // Alertas do sistema
        $alertas = [];

        // Verificar instâncias desconectadas
        $desconectadas = $instancias->where('is_connected', false)->count();
        if ($desconectadas > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'icone' => 'fas fa-plug',
                'titulo' => 'Instâncias Desconectadas',
                'mensagem' => "{$desconectadas} instância(s) desconectada(s)",
            ];
        }

        // Verificar conversas aguardando há muito tempo
        $aguardandoMuito = Conversa::where('status', 'aguardando')
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();
        if ($aguardandoMuito > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'icone' => 'fas fa-clock',
                'titulo' => 'Clientes Aguardando',
                'mensagem' => "{$aguardandoMuito} conversa(s) aguardando há mais de 30 minutos",
            ];
        }

        // Verificar chats sem contato
        $chatsSemContato = Chat::whereNotIn('id', function ($q) {
                $q->select('chat_id')->from('conversas');
            })
            ->where('chat_type', 'individual')
            ->where('created_at', '>', now()->subDays(7))
            ->count();
        if ($chatsSemContato > 5) {
            $alertas[] = [
                'tipo' => 'info',
                'icone' => 'fas fa-user-slash',
                'titulo' => 'Chats sem Conversa',
                'mensagem' => "{$chatsSemContato} chats recentes sem conversa ativa",
            ];
        }

        // Verificar erros recentes
        $errosHoje = collect($errosRecentes)->filter(function ($e) {
            return strpos($e['data'], date('Y-m-d')) === 0 && $e['tipo'] === 'ERROR';
        })->count();
        if ($errosHoje > 10) {
            $alertas[] = [
                'tipo' => 'danger',
                'icone' => 'fas fa-exclamation-triangle',
                'titulo' => 'Muitos Erros',
                'mensagem' => "{$errosHoje} erros no log hoje",
            ];
        }

        // Estatísticas gerais
        $stats = [
            'total_chats' => Chat::count(),
            'total_contatos' => Contact::count(),
            'total_mensagens' => Message::count(),
            'total_aliases' => ContactAlias::count(),
            'mensagens_hoje' => Message::whereDate('created_at', today())->count(),
            'conversas_hoje' => Conversa::whereDate('created_at', today())->count(),
        ];

        return view('admin.monitor.saude', compact(
            'statusInstancias',
            'errosRecentes',
            'alertas',
            'stats'
        ));
    }
}
