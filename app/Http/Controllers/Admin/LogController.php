<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogSistema;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $query = LogSistema::query();

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('nivel')) {
            $query->where('nivel', $request->nivel);
        }

        if ($request->filled('instancia')) {
            $query->where('instancia', $request->instancia);
        }

        if ($request->filled('search')) {
            $query->where('mensagem', 'like', '%' . $request->search . '%');
        }

        $logs = $query->orderBy('criada_em', 'desc')->paginate(50)->withQueryString();

        // Estatísticas
        $stats = [
            'total' => LogSistema::count(),
            'erros' => LogSistema::where('nivel', 'error')->where('criada_em', '>=', now()->subDay())->count(),
            'webhooks_hoje' => LogSistema::where('tipo', 'webhook')->where('criada_em', '>=', now()->startOfDay())->count(),
            'conexoes' => LogSistema::where('tipo', 'conexao')->where('criada_em', '>=', now()->subDay())->count(),
        ];

        return view('admin.logs.index', compact('logs', 'stats'));
    }

    public function show(LogSistema $log)
    {
        return view('admin.logs.show', compact('log'));
    }

    public function limpar()
    {
        $deleted = LogSistema::limparAntigos(30);

        return redirect()->route('admin.logs')
            ->with('success', "Removidos {$deleted} logs com mais de 30 dias!");
    }
}
