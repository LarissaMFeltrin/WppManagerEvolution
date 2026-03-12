<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Conversa;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accountIds = WhatsappAccount::where('empresa_id', $user->empresa_id)->pluck('id');

        $query = Conversa::with(['atendente', 'account'])
            ->whereIn('account_id', $accountIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('atendente_id')) {
            $query->where('atendente_id', $request->atendente_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            // Buscar IDs de chats que correspondem à busca (para grupos)
            $chatIds = Chat::where('chat_name', 'like', "%{$search}%")
                ->orWhere('chat_id', 'like', "%{$search}%")
                ->pluck('id');

            $query->where(function ($q) use ($search, $chatIds) {
                $q->where('cliente_nome', 'like', "%{$search}%")
                    ->orWhere('cliente_numero', 'like', "%{$search}%");

                // Incluir conversas de grupos que correspondem à busca
                if ($chatIds->isNotEmpty()) {
                    $q->orWhereIn('chat_id', $chatIds);
                }
            });
        }

        $conversas = $query->orderBy('created_at', 'desc')->paginate(20);

        $atendentes = User::where('empresa_id', $user->empresa_id)
            ->where('role', 'agent')
            ->orderBy('name')
            ->get();

        $aguardando = Conversa::whereIn('account_id', $accountIds)->where('status', 'aguardando')->count();

        return view('admin.conversas.index', compact('conversas', 'atendentes', 'aguardando'));
    }

    public function show(Conversa $conversa)
    {
        $conversa->load(['atendente', 'account', 'chat.messages']);

        return view('admin.conversas.show', compact('conversa'));
    }

    public function atender(Conversa $conversa)
    {
        $user = Auth::user();

        if ($conversa->status !== 'aguardando') {
            return redirect()->back()->with('error', 'Esta conversa ja esta sendo atendida!');
        }

        $conversa->update([
            'atendente_id' => $user->id,
            'status' => 'em_atendimento',
            'atendida_em' => now(),
        ]);

        $user->increment('conversas_ativas');

        return redirect()->route('admin.chat', ['conversa' => $conversa->id])
            ->with('success', 'Conversa iniciada!');
    }

    public function finalizar(Conversa $conversa)
    {
        $user = Auth::user();

        $conversa->update([
            'status' => 'finalizada',
            'finalizada_em' => now(),
        ]);

        if ($conversa->atendente_id) {
            User::where('id', $conversa->atendente_id)->decrement('conversas_ativas');
        }

        return redirect()->route('admin.conversas.index')
            ->with('success', 'Conversa finalizada!');
    }

    public function transferir(Request $request, Conversa $conversa)
    {
        $antigoAtendente = $conversa->atendente_id;

        // Devolver para fila
        if ($request->boolean('devolver_fila')) {
            $conversa->update([
                'atendente_id' => null,
                'status' => 'aguardando',
                'devolvida_por' => $antigoAtendente,
                'cliente_aguardando_desde' => now(),
            ]);

            if ($antigoAtendente) {
                User::where('id', $antigoAtendente)->decrement('conversas_ativas');
            }

            if ($request->ajax()) {
                return response()->json(['success' => true]);
            }

            return redirect()->back()->with('success', 'Conversa devolvida para a fila!');
        }

        // Transferir para outro atendente
        $validated = $request->validate([
            'atendente_id' => 'required|exists:users,id',
        ]);

        $conversa->update([
            'atendente_id' => $validated['atendente_id'],
            'devolvida_por' => $antigoAtendente,
        ]);

        if ($antigoAtendente) {
            User::where('id', $antigoAtendente)->decrement('conversas_ativas');
        }
        User::where('id', $validated['atendente_id'])->increment('conversas_ativas');

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Conversa transferida!');
    }
}
