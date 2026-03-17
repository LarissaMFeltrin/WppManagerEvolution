<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\WhatsappAccount;
use App\Services\EvolutionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsappAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $query = WhatsappAccount::with('empresa');

        if ($user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $accounts = $query->orderBy('session_name')->paginate(15);

        return view('admin.whatsapp.index', compact('accounts'));
    }

    public function create()
    {
        $empresas = Empresa::where('status', true)->orderBy('nome')->get();

        return view('admin.whatsapp.create', compact('empresas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'session_name' => 'required|string|max:100|unique:whatsapp_accounts,session_name',
            'empresa_id' => 'required|exists:empresas,id',
            'phone_number' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $sessionName = $validated['session_name'];

        // Criar instância na Evolution API
        try {
            $service = app(EvolutionApiService::class);
            $result = $service->createInstance($sessionName);

            if (!($result['success'] ?? false)) {
                return back()->withInput()->with('error', 'Erro ao criar instancia na Evolution API: ' . ($result['error'] ?? 'Erro desconhecido'));
            }

            // Configurar webhook automaticamente
            $service->configureWebhook($sessionName);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Erro ao criar instancia: ' . $e->getMessage());
        }

        WhatsappAccount::create([
            'session_name' => $sessionName,
            'empresa_id' => $validated['empresa_id'],
            'phone_number' => $validated['phone_number'] ?? '',
            'user_id' => Auth::id(),
            'is_active' => $validated['is_active'] ?? true,
            'is_connected' => false,
        ]);

        return redirect()->route('admin.whatsapp.index')
            ->with('success', 'Instancia criada com sucesso! Conecte via QR Code ou Codigo de Pareamento.');
    }

    public function edit(WhatsappAccount $whatsapp)
    {
        $empresas = Empresa::where('status', true)->orderBy('nome')->get();

        return view('admin.whatsapp.edit', compact('whatsapp', 'empresas'));
    }

    public function update(Request $request, WhatsappAccount $whatsapp)
    {
        $validated = $request->validate([
            'session_name' => 'required|string|max:100|unique:whatsapp_accounts,session_name,' . $whatsapp->id,
            'empresa_id' => 'required|exists:empresas,id',
            'is_active' => 'boolean',
        ]);

        $whatsapp->update($validated);

        return redirect()->route('admin.whatsapp.index')
            ->with('success', 'Instancia atualizada com sucesso!');
    }

    public function destroy(WhatsappAccount $whatsapp)
    {
        if ($whatsapp->conversas()->count() > 0) {
            return redirect()->route('admin.whatsapp.index')
                ->with('error', 'Nao e possivel excluir instancia com conversas vinculadas!');
        }

        $whatsapp->delete();

        return redirect()->route('admin.whatsapp.index')
            ->with('success', 'Instancia excluida com sucesso!');
    }

    public function connect(WhatsappAccount $whatsapp)
    {
        try {
            $service = app(EvolutionApiService::class);
            $result = $service->connectInstance($whatsapp->session_name);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function disconnect(WhatsappAccount $whatsapp)
    {
        try {
            $service = app(EvolutionApiService::class);
            $result = $service->disconnectInstance($whatsapp->session_name);

            $whatsapp->update(['is_connected' => false]);

            return redirect()->route('admin.whatsapp.index')
                ->with('success', 'Instancia desconectada!');
        } catch (\Exception $e) {
            return redirect()->route('admin.whatsapp.index')
                ->with('error', 'Erro ao desconectar: ' . $e->getMessage());
        }
    }

    public function qrcode(WhatsappAccount $whatsapp)
    {
        try {
            $service = app(EvolutionApiService::class);
            $result = $service->getQrCode($whatsapp->session_name);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Conectar via Pairing Code (número de telefone)
     * Retorna um código de 8 dígitos para inserir no WhatsApp
     */
    public function pairingCode(Request $request, WhatsappAccount $whatsapp)
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:10|max:20',
        ]);

        try {
            $service = app(EvolutionApiService::class);
            $result = $service->connectWithPairingCode(
                $whatsapp->session_name,
                $validated['phone_number']
            );

            // Salvar o número na instância para referência
            if ($result['success']) {
                $whatsapp->update(['phone_number' => $validated['phone_number']]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function restart(WhatsappAccount $whatsapp)
    {
        try {
            $service = app(EvolutionApiService::class);
            $result = $service->restartInstance($whatsapp->session_name);

            return redirect()->route('admin.whatsapp.index')
                ->with('success', 'Instancia reiniciada! Aguarde alguns segundos para reconectar.');
        } catch (\Exception $e) {
            return redirect()->route('admin.whatsapp.index')
                ->with('error', 'Erro ao reiniciar: ' . $e->getMessage());
        }
    }

    public function checkStatus()
    {
        $user = Auth::user();
        $service = app(EvolutionApiService::class);

        $query = WhatsappAccount::query();
        if ($user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $accounts = $query->get();
        $results = [];

        foreach ($accounts as $account) {
            $wasConnected = $account->is_connected;
            $isConnected = false;

            try {
                $result = $service->getConnectionState($account->session_name);
                $state = $result['data']['instance']['state'] ?? 'unknown';
                $isConnected = $state === 'open';

                if ($account->is_connected !== $isConnected) {
                    $account->update([
                        'is_connected' => $isConnected,
                        'last_connection' => $isConnected ? now() : $account->last_connection,
                    ]);
                }
            } catch (\Exception $e) {
                if ($account->is_connected) {
                    $account->update(['is_connected' => false]);
                }
            }

            $results[] = [
                'id' => $account->id,
                'session_name' => $account->session_name,
                'is_connected' => $isConnected,
                'was_connected' => $wasConnected,
                'just_disconnected' => $wasConnected && !$isConnected,
            ];
        }

        return response()->json(['instances' => $results]);
    }
}
