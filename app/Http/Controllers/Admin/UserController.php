<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('empresa')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $empresas = Empresa::where('status', true)->orderBy('nome')->get();
        $whatsappAccounts = WhatsappAccount::orderBy('session_name')->get();

        return view('admin.users.create', compact('empresas', 'whatsappAccounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'empresa_id' => 'nullable|exists:empresas,id',
            'role' => 'required|in:admin,supervisor,agent',
            'status_atendimento' => 'required|in:online,offline,ocupado',
            'max_conversas' => 'required|integer|min:1|max:50',
            'whatsapp_accounts' => 'nullable|array',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'empresa_id' => $validated['empresa_id'],
            'role' => $validated['role'],
            'status_atendimento' => $validated['status_atendimento'],
            'max_conversas' => $validated['max_conversas'],
        ]);

        if (!empty($validated['whatsapp_accounts'])) {
            $user->whatsappAccounts()->sync($validated['whatsapp_accounts']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario criado com sucesso!');
    }

    public function edit(User $user)
    {
        $currentUser = Auth::user();

        // Agent só pode editar o próprio perfil
        if ($currentUser->isAgent() && $currentUser->id !== $user->id) {
            abort(403, 'Você só pode editar seu próprio perfil.');
        }

        $empresas = Empresa::where('status', true)->orderBy('nome')->get();
        $whatsappAccounts = WhatsappAccount::orderBy('session_name')->get();
        $userAccounts = $user->whatsappAccounts->pluck('id')->toArray();

        return view('admin.users.edit', compact('user', 'empresas', 'whatsappAccounts', 'userAccounts'));
    }

    public function update(Request $request, User $user)
    {
        $currentUser = Auth::user();

        // Agent só pode editar o próprio perfil
        if ($currentUser->isAgent() && $currentUser->id !== $user->id) {
            abort(403, 'Você só pode editar seu próprio perfil.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6|confirmed',
            'empresa_id' => 'nullable|exists:empresas,id',
            'role' => 'required|in:admin,supervisor,agent',
            'status_atendimento' => 'required|in:online,offline,ocupado',
            'max_conversas' => 'required|integer|min:1|max:50',
            'whatsapp_accounts' => 'nullable|array',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status_atendimento' => $validated['status_atendimento'],
        ];

        // Só admin/supervisor podem alterar role, empresa, max_conversas e contas
        if (!$currentUser->isAgent()) {
            $data['empresa_id'] = $validated['empresa_id'];
            $data['role'] = $validated['role'];
            $data['max_conversas'] = $validated['max_conversas'];
        }

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        // Só admin/supervisor podem alterar contas WhatsApp vinculadas
        if (!$currentUser->isAgent()) {
            $user->whatsappAccounts()->sync($validated['whatsapp_accounts'] ?? []);
        }

        // Agent volta pro painel, admin/supervisor volta pra lista
        $redirect = $currentUser->isAgent()
            ? redirect()->route('admin.painel')
            : redirect()->route('admin.users.index');

        return $redirect->with('success', 'Usuario atualizado com sucesso!');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Voce nao pode excluir seu proprio usuario!');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario excluido com sucesso!');
    }
}
