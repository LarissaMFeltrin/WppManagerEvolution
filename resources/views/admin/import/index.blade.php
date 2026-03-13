@extends('adminlte::page')

@section('title', 'Importar Histórico')

@section('content_header')
    <h1>Importar Histórico</h1>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-import me-2"></i>
                        Importar Histórico do WhatsApp
                    </h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            {{ session('error') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Corrija os erros abaixo:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.import.store') }}" method="POST" enctype="multipart/form-data" id="importForm">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-bold">1. Arquivo exportado (.txt ou .zip)</label>

                            <!-- Tabs para escolher método -->
                            <ul class="nav nav-tabs mb-2" id="fileMethodTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="upload-tab" data-toggle="tab" href="#uploadTab" role="tab" aria-controls="uploadTab" aria-selected="true">Upload</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="path-tab" data-toggle="tab" href="#pathTab" role="tab" aria-controls="pathTab" aria-selected="false">Caminho no servidor</a>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="uploadTab" role="tabpanel" aria-labelledby="upload-tab">
                                    <input type="file" name="file" id="fileInput" class="form-control @error('file') is-invalid @enderror" accept=".txt,.zip">
                                    <small class="form-text text-muted">
                                        <strong>.txt</strong> = só mensagens |
                                        <strong>.zip</strong> = mensagens + mídias
                                    </small>
                                </div>
                                <div class="tab-pane fade" id="pathTab" role="tabpanel" aria-labelledby="path-tab">
                                    <input type="text" name="file_path" id="filePath" class="form-control @error('file_path') is-invalid @enderror"
                                           placeholder="/caminho/completo/para/arquivo.zip" value="{{ old('file_path') }}">
                                    <small class="form-text text-muted">
                                        Caminho absoluto do arquivo no servidor (para arquivos grandes)
                                    </small>
                                </div>
                            </div>

                            @error('file')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            @error('file_path')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Análise do arquivo -->
                        <div id="fileAnalysis" class="mb-4" style="display: none;">
                            <div class="alert alert-info">
                                <h6 class="alert-heading mb-2">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Análise do arquivo
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Total de mensagens:</strong>
                                        <span id="totalMessages" class="badge bg-primary">0</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Arquivos de mídia:</strong>
                                        <span id="mediaFiles" class="badge bg-success">0</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Período:</strong>
                                        <span id="period">-</span>
                                    </div>
                                </div>
                                <hr>
                                <div id="sendersList">
                                    <strong>Participantes:</strong>
                                    <ul class="mb-0 mt-2" id="sendersUl"></ul>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">2. Instância WhatsApp</label>
                                <select name="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                                    <option value="">Selecione...</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->session_name }} ({{ $account->phone_number }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">3. Número do contato</label>
                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                       placeholder="5544999999999" value="{{ old('phone') }}" required>
                                <div class="form-text">Número completo com DDD e DDI</div>
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">4. Seu nome no WhatsApp</label>
                                <input type="text" name="owner_name" id="ownerName" class="form-control @error('owner_name') is-invalid @enderror"
                                       placeholder="Como aparece no export" value="{{ old('owner_name') }}" required>
                                <div class="form-text">Exatamente como aparece no arquivo exportado (clique no nome acima)</div>
                                @error('owner_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">5. Nome do contato (opcional)</label>
                                <input type="text" name="contact_name" class="form-control"
                                       placeholder="Nome para salvar no sistema" value="{{ old('contact_name') }}">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-upload me-2"></i>
                                Importar Histórico
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-question-circle me-2"></i>
                        Como exportar do WhatsApp
                    </h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-2">Abra a conversa no WhatsApp</li>
                        <li class="mb-2">Toque no menu (3 pontos)</li>
                        <li class="mb-2">Selecione "Mais" > "Exportar conversa"</li>
                        <li class="mb-2">Escolha:
                            <ul>
                                <li><strong>Sem mídia</strong> → arquivo .txt</li>
                                <li><strong>Incluir mídia</strong> → arquivo .zip</li>
                            </ul>
                        </li>
                        <li class="mb-2">Salve ou envie o arquivo</li>
                        <li>Faça upload aqui</li>
                    </ol>

                    <hr>

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Formatos suportados:</strong>
                        <ul class="mb-0 mt-2 small">
                            <li><strong>.txt</strong> - Apenas texto</li>
                            <li><strong>.zip</strong> - Texto + mídias (fotos, áudios, vídeos)</li>
                            <li>Máximo 500MB por arquivo</li>
                            <li>Mensagens duplicadas são ignoradas</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
document.getElementById('fileInput').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const analysisDiv = document.getElementById('fileAnalysis');
    analysisDiv.style.display = 'none';

    // Criar FormData para análise
    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

    try {
        const response = await fetch('{{ route("admin.import.analyze") }}', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('totalMessages').textContent = data.total_messages;
            document.getElementById('mediaFiles').textContent = data.media_files || 0;
            document.getElementById('period').textContent =
                data.period.start && data.period.end
                    ? `${data.period.start} a ${data.period.end}`
                    : '-';

            // Listar participantes
            const ul = document.getElementById('sendersUl');
            ul.innerHTML = '';
            for (const [sender, count] of Object.entries(data.senders)) {
                const li = document.createElement('li');
                li.innerHTML = `<a href="#" class="sender-link" data-name="${sender}">${sender}</a>: ${count} mensagens`;
                ul.appendChild(li);
            }

            // Click para preencher owner_name
            document.querySelectorAll('.sender-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('ownerName').value = this.dataset.name;
                });
            });

            analysisDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Erro ao analisar arquivo:', error);
    }
});
</script>
@stop

@section('css')
<style>
.sender-link {
    color: inherit;
    text-decoration: underline;
}
.sender-link:hover {
    color: var(--bs-primary);
}
</style>
@stop
