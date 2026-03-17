<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Conversa;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $empresaId = $user->empresa_id;

        $accountIds = $user->getAccountIds();

        $stats = [
            'total_chats' => Chat::whereIn('account_id', $accountIds)->count(),
            'mensagens_hoje' => Message::whereHas('chat', fn($q) => $q->whereIn('account_id', $accountIds))
                ->whereDate('created_at', today())
                ->count(),
            'instancias_online' => WhatsappAccount::where('empresa_id', $empresaId)
                ->where('is_connected', true)
                ->count(),
            'conversas_ativas' => Conversa::whereIn('account_id', $accountIds)
                ->whereIn('status', ['aguardando', 'em_atendimento'])
                ->count(),
        ];

        // Ultimas mensagens
        $ultimasMensagens = Message::with(['chat'])
            ->whereHas('chat', fn($q) => $q->whereIn('account_id', $accountIds))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Instancias WhatsApp
        $instancias = WhatsappAccount::where('empresa_id', $empresaId)
            ->orderBy('session_name')
            ->get();

        return view('admin.dashboard', compact('stats', 'ultimasMensagens', 'instancias'));
    }
}
