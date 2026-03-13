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
                        <div class="alert alert-success alert-dismissible fade show" id="successAlert">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Sucesso!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Erro!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
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
                                    <div class="input-group">
                                        <input type="text" name="file_path" id="filePath" class="form-control @error('file_path') is-invalid @enderror"
                                               placeholder="/caminho/completo/para/arquivo.zip" value="{{ old('file_path') }}">
                                        <button type="button" class="btn btn-outline-secondary" id="analyzePathBtn">
                                            <i class="fas fa-search"></i> Analisar
                                        </button>
                                    </div>
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
                                    <span id="clickHint" class="text-muted small ms-2" style="display: none;">
                                        (clique no SEU nome para preencher os campos)
                                    </span>
                                    <ul class="mb-0 mt-2" id="sendersUl"></ul>
                                </div>

                                <!-- Filtro de período -->
                                <hr>
                                <div class="mt-3">
                                    <strong><i class="fas fa-calendar-alt me-1"></i> Período a importar:</strong>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="radio" name="period_type" id="periodAll" value="all" checked>
                                        <label class="form-check-label" for="periodAll">
                                            Importar tudo (<span id="allCount">0</span> mensagens)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="period_type" id="periodCustom" value="custom">
                                        <label class="form-check-label" for="periodCustom">
                                            Selecionar período
                                        </label>
                                    </div>
                                    <div id="periodFields" class="row mt-2" style="display: none;">
                                        <div class="col-md-6">
                                            <label class="form-label small">Data inicial:</label>
                                            <input type="date" name="date_from" id="dateFrom" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Data final:</label>
                                            <input type="date" name="date_to" id="dateTo" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12 mt-2">
                                            <span class="text-muted small">
                                                <i class="fas fa-info-circle"></i>
                                                Estimativa: <strong id="estimatedCount">-</strong> mensagens no período selecionado
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">2. Tipo de conversa</label>
                                <select name="chat_type" id="chatType" class="form-select" required>
                                    <option value="individual" {{ old('chat_type', 'individual') == 'individual' ? 'selected' : '' }}>Individual</option>
                                    <option value="group" {{ old('chat_type') == 'group' ? 'selected' : '' }}>Grupo</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">3. Instância WhatsApp</label>
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

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" id="phoneLabel">4. Número do contato</label>
                                <input type="text" name="phone" id="phoneInput" class="form-control @error('phone') is-invalid @enderror"
                                       placeholder="5544999999999" value="{{ old('phone') }}" required>
                                <div class="form-text" id="phoneHelp">Número completo com DDD e DDI</div>
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">5. Seu nome no WhatsApp</label>
                                <input type="text" name="owner_name" id="ownerName" class="form-control @error('owner_name') is-invalid @enderror"
                                       placeholder="Como aparece no export" value="{{ old('owner_name') }}" required>
                                <div class="form-text">Exatamente como aparece no arquivo exportado (clique no nome acima)</div>
                                @error('owner_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3" id="contactNameDiv">
                                <label class="form-label fw-bold" id="contactNameLabel">6. Nome do contato (opcional)</label>
                                <input type="text" name="contact_name" id="contactNameInput" class="form-control"
                                       placeholder="Nome para salvar no sistema" value="{{ old('contact_name') }}">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-upload me-2" id="submitIcon"></i>
                                <span id="submitText">Importar Histórico</span>
                            </button>
                        </div>

                        <!-- Loading overlay -->
                        <div id="importLoading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Processando...</span>
                            </div>
                            <p class="mt-3 text-muted">
                                <strong>Importando mensagens...</strong><br>
                                <small>Isso pode demorar alguns minutos para arquivos grandes</small>
                            </p>
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
// Dados da análise para uso global
let analysisData = null;
let currentFileName = null;

// Extrair número de telefone de uma string
function extractPhoneNumber(text) {
    if (!text) return null;
    // Padrões: +55 44 99999-9999, 5544999999999, (44) 99999-9999
    const patterns = [
        /\+?(\d{2})\s*(\d{2})\s*(\d{4,5})[-\s]?(\d{4})/,  // +55 44 99999-9999
        /\((\d{2})\)\s*(\d{4,5})[-\s]?(\d{4})/,           // (44) 99999-9999
        /(\d{10,13})/                                      // 5544999999999
    ];

    for (const pattern of patterns) {
        const match = text.match(pattern);
        if (match) {
            // Limpar e retornar apenas números
            return match[0].replace(/\D/g, '');
        }
    }
    return null;
}

// Função para exibir os dados da análise
function displayAnalysisData(data, filename = null) {
    analysisData = data;
    currentFileName = filename;
    const analysisDiv = document.getElementById('fileAnalysis');

    document.getElementById('totalMessages').textContent = data.total_messages;
    document.getElementById('allCount').textContent = data.total_messages;
    document.getElementById('mediaFiles').textContent = data.media_files || 0;
    document.getElementById('period').textContent =
        data.period.start && data.period.end
            ? `${data.period.start} a ${data.period.end}`
            : '-';

    // Preencher datas min/max nos inputs
    if (data.period.start_iso && data.period.end_iso) {
        document.getElementById('dateFrom').value = data.period.start_iso;
        document.getElementById('dateFrom').min = data.period.start_iso;
        document.getElementById('dateFrom').max = data.period.end_iso;
        document.getElementById('dateTo').value = data.period.end_iso;
        document.getElementById('dateTo').min = data.period.start_iso;
        document.getElementById('dateTo').max = data.period.end_iso;
    }

    // Listar participantes
    const ul = document.getElementById('sendersUl');
    ul.innerHTML = '';
    const sendersList = Object.entries(data.senders);

    for (const [sender, count] of sendersList) {
        const li = document.createElement('li');
        li.innerHTML = `<a href="#" class="sender-link" data-name="${sender}">${sender}</a>: ${count} mensagens`;
        ul.appendChild(li);
    }

    // Click para preencher owner_name (clique no SEU nome)
    document.querySelectorAll('.sender-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const clickedName = this.dataset.name;
            document.getElementById('ownerName').value = clickedName;

            // Se há 2 participantes, o outro é automaticamente o contato
            if (sendersList.length === 2) {
                const otherSender = sendersList.find(([name]) => name !== clickedName);
                if (otherSender) {
                    const contactNameInput = document.querySelector('input[name="contact_name"]');
                    if (contactNameInput) {
                        contactNameInput.value = otherSender[0];
                    }
                }
            }

            // Feedback visual
            document.querySelectorAll('.sender-link').forEach(l => l.classList.remove('fw-bold', 'text-primary'));
            this.classList.add('fw-bold', 'text-primary');
        });
    });

    // Auto-preencher campos
    autoFillFields(data, filename, sendersList);

    analysisDiv.style.display = 'block';
}

// Auto-preencher campos baseado na análise
// Retorna true se conseguiu identificar owner/contact, false se o usuário precisa clicar
function autoFillFields(data, filename, sendersList) {
    const phoneInput = document.querySelector('input[name="phone"]');
    const contactNameInput = document.querySelector('input[name="contact_name"]');
    const ownerNameInput = document.getElementById('ownerName');
    const clickHint = document.getElementById('clickHint');

    let autoFilled = false;

    // Tentar extrair número do nome do arquivo (ex: WhatsAppChat5544999999999.zip)
    if (filename && phoneInput && !phoneInput.value) {
        const phone = extractPhoneNumber(filename);
        if (phone) {
            phoneInput.value = phone;
        }
    }

    // Se há exatamente 2 participantes
    if (sendersList.length === 2) {
        const [sender1, sender2] = sendersList;

        // Verificar se algum participante tem nome que parece telefone
        const phone1 = extractPhoneNumber(sender1[0]);
        const phone2 = extractPhoneNumber(sender2[0]);

        if (phone1 && !phone2) {
            // sender1 tem nome = número, é o contato
            if (phoneInput && !phoneInput.value) phoneInput.value = phone1;
            if (contactNameInput && !contactNameInput.value) contactNameInput.value = sender1[0];
            if (ownerNameInput && !ownerNameInput.value) ownerNameInput.value = sender2[0];
            autoFilled = true;
        } else if (phone2 && !phone1) {
            // sender2 tem nome = número, é o contato
            if (phoneInput && !phoneInput.value) phoneInput.value = phone2;
            if (contactNameInput && !contactNameInput.value) contactNameInput.value = sender2[0];
            if (ownerNameInput && !ownerNameInput.value) ownerNameInput.value = sender1[0];
            autoFilled = true;
        }
        // Se ambos têm nome (não são números), NÃO auto-preencher
        // O usuário deve clicar no seu nome na lista de participantes
    }

    // Mostrar dica se não foi possível auto-preencher
    if (clickHint) {
        clickHint.style.display = autoFilled ? 'none' : 'inline';
    }

    return autoFilled;
}

// Analisar arquivo de upload
document.getElementById('fileInput').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;

    document.getElementById('fileAnalysis').style.display = 'none';

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
            displayAnalysisData(data, file.name);
        } else {
            alert(data.error || 'Erro ao analisar arquivo');
        }
    } catch (error) {
        console.error('Erro ao analisar arquivo:', error);
    }
});

// Analisar arquivo do caminho no servidor
document.getElementById('analyzePathBtn').addEventListener('click', async function() {
    const filePath = document.getElementById('filePath').value;
    if (!filePath) {
        alert('Informe o caminho do arquivo');
        return;
    }

    document.getElementById('fileAnalysis').style.display = 'none';
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analisando...';

    // Extrair nome do arquivo do caminho
    const fileName = filePath.split('/').pop();

    try {
        const response = await fetch('{{ route("admin.import.analyzePath") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ file_path: filePath })
        });
        const data = await response.json();
        if (data.success) {
            displayAnalysisData(data, fileName);
        } else {
            alert(data.error || 'Erro ao analisar arquivo');
        }
    } catch (error) {
        console.error('Erro ao analisar arquivo:', error);
        alert('Erro ao analisar arquivo');
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-search"></i> Analisar';
    }
});

// Controle dos campos de período
document.querySelectorAll('input[name="period_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const periodFields = document.getElementById('periodFields');
        if (this.value === 'custom') {
            periodFields.style.display = 'flex';
        } else {
            periodFields.style.display = 'none';
        }
    });
});

// Estimar mensagens no período selecionado
function updateEstimate() {
    if (!analysisData || !analysisData.messages_by_date) return;

    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;

    if (!dateFrom || !dateTo) return;

    let count = 0;
    for (const [date, msgCount] of Object.entries(analysisData.messages_by_date)) {
        if (date >= dateFrom && date <= dateTo) {
            count += msgCount;
        }
    }

    document.getElementById('estimatedCount').textContent = count;
}

document.getElementById('dateFrom').addEventListener('change', updateEstimate);
document.getElementById('dateTo').addEventListener('change', updateEstimate);

// Loading durante submit
document.getElementById('importForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    const submitIcon = document.getElementById('submitIcon');
    const submitText = document.getElementById('submitText');
    const loadingDiv = document.getElementById('importLoading');

    // Desabilitar botão e mostrar loading
    submitBtn.disabled = true;
    submitIcon.className = 'fas fa-spinner fa-spin me-2';
    submitText.textContent = 'Processando...';
    loadingDiv.style.display = 'block';
});

// Scroll para topo se houver mensagem de sucesso ou erro
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.querySelector('.alert-danger');

    if (successAlert || errorAlert) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});

// Mudar labels baseado no tipo de chat
document.getElementById('chatType').addEventListener('change', function() {
    const isGroup = this.value === 'group';
    const phoneLabel = document.getElementById('phoneLabel');
    const phoneInput = document.getElementById('phoneInput');
    const phoneHelp = document.getElementById('phoneHelp');
    const contactNameDiv = document.getElementById('contactNameDiv');
    const contactNameLabel = document.getElementById('contactNameLabel');

    if (isGroup) {
        phoneLabel.textContent = '4. ID do Grupo';
        phoneInput.placeholder = '5544999999999-1234567890';
        phoneHelp.textContent = 'ID do grupo (número-timestamp) ou deixe vazio para gerar';
        phoneInput.required = false;
        contactNameLabel.textContent = '6. Nome do Grupo';
        document.getElementById('contactNameInput').placeholder = 'Nome do grupo';
    } else {
        phoneLabel.textContent = '4. Número do contato';
        phoneInput.placeholder = '5544999999999';
        phoneHelp.textContent = 'Número completo com DDD e DDI';
        phoneInput.required = true;
        contactNameLabel.textContent = '6. Nome do contato (opcional)';
        document.getElementById('contactNameInput').placeholder = 'Nome para salvar no sistema';
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
#importLoading {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    margin-top: 1rem;
}
.alert-success {
    border-left: 4px solid #198754;
}
.alert-danger {
    border-left: 4px solid #dc3545;
}
</style>
@stop
