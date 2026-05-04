<?php
session_start();
require 'conexao.php';

// 1. LOGOUT
if(isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

// 2. LOGIN
if(isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT u.*, un.nome as nome_unidade, un.tipo FROM usuarios u JOIN unidades un ON u.unidade_id = un.id WHERE u.login = ? AND u.senha = ?");
    $stmt->execute([$_POST['user'], $_POST['senha']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user) {
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['unidade_id'] = $user['unidade_id'];
        $_SESSION['nome_unidade'] = $user['nome_unidade'];
        $_SESSION['tipo_unidade'] = $user['tipo']; 
        header("Location: admin.php"); 
        exit;
    } else {
        $erro_login = "Login ou senha incorretos!";
    }
}

// 3. BARREIRA DE ACESSO
if(!isset($_SESSION['usuario_id'])) {
    echo '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head><meta charset="UTF-8"><title>Acesso Restrito</title><link rel="stylesheet" href="style.css"><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"></head>
    <body style="margin:0; padding:0; display:flex; min-height:100vh; background-image: url(\'fundo-medicos.jpg\'); background-size: cover; align-items:center; justify-content:center;">
        <div style="background:rgba(255,255,255,0.96); padding:40px; border-radius:16px; width:100%; max-width:450px; text-align:center;">
            <div style="font-size:40px; color:#2b78ce; margin-bottom:10px;"><i class="fas fa-hospital-alt"></i></div>
            <h2 style="margin-bottom:5px; color:#1a202c;">Rede Municipal</h2>
            <p style="color:#8892a0; font-size:14px; margin-bottom:30px;">Acesso ao Sistema</p>
            '.(isset($erro_login) ? "<div style='background:#fee2e2; color:#dc2626; padding:10px; border-radius:8px; margin-bottom:20px;'>$erro_login</div>" : "").'
            <form method="POST" style="text-align:left;">
                <label style="font-weight:bold; font-size:13px;">Usuário:</label>
                <input type="text" name="user" class="form-control" style="margin-bottom:15px;" required>
                <label style="font-weight:bold; font-size:13px;">Senha:</label>
                <input type="password" name="senha" class="form-control" style="margin-bottom:20px;" required>
                <button type="submit" name="login" class="btn btn-primary" style="width:100%; padding:14px; border-radius:8px; font-weight:bold;">Entrar no Sistema</button>
            </form>
            <div style="margin-top:20px;"><a href="index.php" style="color:#4a5568; font-size:12px; font-weight:bold; text-decoration:none;"><i class="fas fa-arrow-left"></i> Voltar ao Portal</a></div>
        </div>
    </body>
    </html>';
    exit;
}

$unidades_todas = $pdo->query("SELECT * FROM unidades")->fetchAll(PDO::FETCH_ASSOC);
$pacientes = $pdo->query("SELECT * FROM pacientes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$medicos_cem = $pdo->query("SELECT m.* FROM medicos m JOIN unidades u ON m.unidade_id = u.id WHERE u.tipo = 'CENTRAL' ORDER BY m.especialidade ASC, m.nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// SE FOR CENTRAL, TRAZ O CALENDÁRIO DA REDE INTEIRA (UBS + CEM)
if ($_SESSION['tipo_unidade'] == 'CENTRAL') {
    $medicos_raw = $pdo->query("SELECT m.*, u.nome as unidade_nome, u.tipo as unidade_tipo FROM medicos m JOIN unidades u ON m.unidade_id = u.id ORDER BY m.nome")->fetchAll(PDO::FETCH_ASSOC);
    
    $consultas = $pdo->query("
        SELECT c.*, p.nome as paciente_nome, p.telefone, p.cpf, p.sus, 
               m.nome as medico_nome, m.especialidade, u.nome as unidade_nome 
        FROM consultas c 
        JOIN pacientes p ON c.paciente_id = p.id 
        JOIN medicos m ON c.medico_id = m.id 
        JOIN unidades u ON c.unidade_id = u.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $encaminhamentos = $pdo->query("SELECT e.*, p.nome as paciente_nome, u.nome as ubs_nome FROM encaminhamentos e JOIN pacientes p ON e.paciente_id = p.id JOIN unidades u ON e.ubs_id = u.id")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // SE FOR UBS, O CÓDIGO TRAVA E SÓ TRAZ AS CONSULTAS DA PRÓPRIA UBS (A UBS NÃO VÊ A CENTRAL)
    $stmt = $pdo->prepare("SELECT m.*, u.nome as unidade_nome, u.tipo as unidade_tipo FROM medicos m JOIN unidades u ON m.unidade_id = u.id WHERE m.unidade_id = ? ORDER BY m.nome");
    $stmt->execute([$_SESSION['unidade_id']]);
    $medicos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $consultas = $pdo->prepare("
        SELECT c.*, p.nome as paciente_nome, p.telefone, p.cpf, p.sus, 
               m.nome as medico_nome, m.especialidade, u.nome as unidade_nome 
        FROM consultas c 
        JOIN pacientes p ON c.paciente_id = p.id 
        JOIN medicos m ON c.medico_id = m.id 
        JOIN unidades u ON c.unidade_id = u.id 
        WHERE c.unidade_id = ?
    ");
    $consultas->execute([$_SESSION['unidade_id']]);
    $consultas = $consultas->fetchAll(PDO::FETCH_ASSOC);
    
    $encaminhamentos = [];
}

// AGRUPAR MÉDICOS POR CRM PARA MÚLTIPLAS LOTAÇÕES
$medicos_agrupados = [];
foreach($medicos_raw as $m) {
    if(!isset($medicos_agrupados[$m['crm']])) {
        $medicos_agrupados[$m['crm']] = [ 'nome' => $m['nome'], 'crm' => $m['crm'], 'vinculos' => [] ];
    }
    $medicos_agrupados[$m['crm']]['vinculos'][] = $m;
}
$medicos = $medicos_raw; 
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= $_SESSION['nome_unidade'] ?> - Sistema</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-agenda { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); position: relative; border-left: 6px solid #cbd5e1; transition: all 0.2s; display: flex; flex-direction: column; gap: 12px; }
        .card-agenda.status-aguardando { border-left-color: #3b82f6; background-color: #f8fafc; }
        .card-agenda.status-confirmado { border-left-color: #10b981; background-color: #ecfdf5; }
        .card-agenda.status-atendido { border-left-color: #059669; background-color: #f0fdf4; opacity: 0.9; }
        .card-agenda.status-cancelado { border-left-color: #ef4444; background-color: #fef2f2; }
        .card-agenda.status-espera { border-left-color: #8b5cf6; background-color: #faf5ff; }
        .agenda-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .agenda-info-paciente h4 { margin: 0 0 5px 0; font-size: 16px; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .agenda-info-paciente p { margin: 0; font-size: 13px; color: #64748b; }
        .hora-badge { background: #e2e8f0; padding: 4px 10px; border-radius: 6px; font-weight: bold; color: #334155; }
        .card-agenda.status-aguardando .hora-badge { background: #dbeafe; color: #1e40af; }
        .card-agenda.status-confirmado .hora-badge { background: #d1fae5; color: #065f46; }
        .badge-status-top { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .bg-badge-aguardando { background: #dbeafe; color: #1e40af; }
        .bg-badge-confirmado { background: #d1fae5; color: #065f46; }
        .bg-badge-cancelado { background: #fee2e2; color: #991b1b; }
        .bg-badge-espera { background: #ede9fe; color: #5b21b6; }
        .bg-badge-atendido { background: #a7f3d0; color: #047857; }
        .agenda-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px; }
        .btn-acao { background: transparent; border: 1px solid #cbd5e1; border-radius: 20px; padding: 6px 15px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
        .btn-outline-green { border-color: #10b981; color: #10b981; } .btn-outline-green:hover { background: #10b981; color: white; }
        .btn-outline-red { border-color: #ef4444; color: #ef4444; } .btn-outline-red:hover { background: #ef4444; color: white; }
        .btn-outline-dark { border-color: #475569; color: #475569; } .btn-outline-dark:hover { background: #475569; color: white; }
        .btn-outline-purple { border-color: #8b5cf6; color: #8b5cf6; } .btn-outline-purple:hover { background: #8b5cf6; color: white; }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon bg-primary"><i class="fas fa-hospital"></i></div>
                <div>
                    <h3 style="font-size: 13px; margin:0; line-height: 1.3; color: white; word-wrap: break-word; max-width: 170px;">
                        <?= $_SESSION['nome_unidade'] ?>
                    </h3>
                    <span style="font-size: 11px; color:#9ca3af;"><?= $_SESSION['tipo_unidade'] == 'CENTRAL' ? 'CEM - Especialidades' : 'Atenção Básica' ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" onclick="mudarAba('dashboard', this)"><i class="fas fa-th-large"></i> Dashboard</a>
                <!-- ADICIONADO O BOTÃO DE VISÃO DE CALENDÁRIO NA BARRA LATERAL -->
                <a href="#" class="nav-item" id="nav-calendario" onclick="mudarAba('calendario', this)"><i class="far fa-calendar-alt"></i> Visão de Calendário</a>
                <a href="#" class="nav-item" id="nav-agenda" onclick="mudarAba('agenda', this)"><i class="fas fa-list-ul"></i> Fila de Atendimentos</a>
                <a href="#" class="nav-item" onclick="mudarAba('pacientes', this)"><i class="fas fa-users"></i> Base de Pacientes</a>
                <a href="#" class="nav-item" onclick="mudarAba('medicos', this)"><i class="fas fa-user-md"></i> Profissionais</a>
                <?php if($_SESSION['tipo_unidade'] == 'CENTRAL'): ?>
                    <a href="#" class="nav-item" onclick="mudarAba('encaminhamentos', this)"><i class="fas fa-inbox"></i> Regulação (Filas)</a>
                <?php endif; ?>
                <div class="nav-divider"></div>
                <a href="index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> Portal ao Público</a>
            </nav>
            <div class="sidebar-footer">
                <a href="?logout=true" class="nav-item"><i class="fas fa-sign-out-alt"></i> Sair do Sistema</a>
            </div>
        </aside>

        <main class="main-content">
            <!-- ABA DASHBOARD -->
            <section id="aba-dashboard" class="aba-conteudo active">
                <header class="page-header"><h2>Painel de Controle</h2><p class="text-muted"><?= date('d/m/Y') ?></p></header>
                <div class="stats-grid">
                    <div class="stat-card bg-blue-light"><div class="stat-info"><span class="stat-label">Total Pacientes (Rede)</span><span class="stat-value"><?= count($pacientes) ?></span></div><div class="stat-icon text-blue"><i class="fas fa-users"></i></div></div>
                    <div class="stat-card bg-green-light"><div class="stat-info"><span class="stat-label">Lotações / Agendas Livres</span><span class="stat-value"><?= count($medicos) ?></span></div><div class="stat-icon text-green"><i class="fas fa-user-md"></i></div></div>
                    
                    <!-- O CARTÃO AGORA TEM EFEITO DE HOVER E É CLICÁVEL (LEVA AO CALENDÁRIO) -->
                    <div class="stat-card bg-yellow-light" style="cursor:pointer; transition: transform 0.2s, box-shadow 0.2s;" onclick="mudarAba('calendario', document.getElementById('nav-calendario'))" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <div class="stat-info">
                            <span class="stat-label">Consultas Agendadas</span>
                            <span class="stat-value"><?= count($consultas) ?></span>
                        </div>
                        <div class="stat-icon text-yellow"><i class="far fa-calendar-check"></i></div>
                    </div>
                </div>
            </section>

            <!-- ============================================== -->
            <!-- NOVA ABA: CALENDÁRIO DE CONSULTAS (INTERATIVO) -->
            <!-- ============================================== -->
            <section id="aba-calendario" class="aba-conteudo" style="display:none;">
                <header class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h2>Calendário Geral de Consultas</h2>
                        <p class="text-muted">Visão panorâmica e rápida dos agendamentos da <?= $_SESSION['tipo_unidade'] == 'CENTRAL' ? 'Rede Municipal' : 'Sua Unidade' ?></p>
                    </div>
                </header>

                <!-- FILTRO (EXCLUSIVO PARA A CENTRAL/CEM VER TODA A REDE) -->
                <?php if($_SESSION['tipo_unidade'] == 'CENTRAL'): ?>
                <div class="card" style="margin-bottom:20px; padding:15px; background:#f8fafc; border-left:4px solid #0284c7;">
                    <label style="font-size:13px; font-weight:bold; color:#475569; display:block; margin-bottom:8px;"><i class="fas fa-filter"></i> Privilégio CEM: Filtrar Calendário por Unidade:</label>
                    <select id="filtroUnidadeCalendario" class="form-control" style="max-width:400px;" onchange="renderizarMesCalendario(); document.getElementById('painelDiaCalendario').innerHTML='<div style=\'text-align:center; color:#94a3b8; padding:40px 0;\'><i class=\'far fa-calendar-check\' style=\'font-size:40px; margin-bottom:10px;\'></i><p>Clique em um dia no calendário para ver os detalhes.</p></div>';">
                        <option value="">👁️ Visualizar Toda a Rede (CEM + UBSs)</option>
                        <?php foreach($unidades_todas as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= $u['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap;">
                    
                    <!-- GRID DO CALENDÁRIO MENSAL -->
                    <div class="card" style="flex:2; min-width:450px; padding:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <button class="btn btn-outline-dark" onclick="mudarMes(-1)"><i class="fas fa-chevron-left"></i> Mês Anterior</button>
                            <h3 id="calMesAno" style="margin:0; font-size:18px; color:#1e293b; font-weight:bold; text-transform:uppercase;"></h3>
                            <button class="btn btn-outline-dark" onclick="mudarMes(1)">Próximo Mês <i class="fas fa-chevron-right"></i></button>
                        </div>
                        <table style="width:100%; border-collapse:collapse; text-align:left;">
                            <thead>
                                <tr>
                                    <th style="padding:10px; color:#64748b; font-size:12px; font-weight:bold; border-bottom:2px solid #cbd5e1; text-align:center;">DOM</th>
                                    <th style="padding:10px; color:#64748b; font-size:12px; font-weight:bold; border-bottom:2px solid #cbd5e1; text-align:center;">SEG</th>
                                    <th style="padding:10px; color:#64748b; font-size:12px; font-weight:bold; border-bottom:2px solid #cbd5e1; text-align:center;">TER</th>
                                    <th style="padding:10px; color:#64748b; font-size:12px; font-weight:bold; border-bottom:2px solid #cbd5e1; text-align:center;">QUA</th>
                                    <th style="padding:10px; color:#64748b; font-size:12px; font-weight:bold; border-bottom:2px solid #cbd5e1; text-align:center;">QUI</th>
                                    <th style="padding:10px; color:#64748b; font-size:12px; font-weight:bold; border-bottom:2px solid #cbd5e1; text-align:center;">SEX</th>
                                    <th style="padding:10px; color:#64748b; font-size:12px; font-weight:bold; border-bottom:2px solid #cbd5e1; text-align:center;">SÁB</th>
                                </tr>
                            </thead>
                            <tbody id="corpoCalendario">
                            </tbody>
                        </table>
                    </div>

                    <!-- PAINEL DE RESUMO DO DIA SELECIONADO -->
                    <div class="card" style="flex:1; min-width:320px; padding:20px; background:#f8fafc; border-left:4px solid #3b82f6; position:sticky; top:20px;" id="painelDiaCalendario">
                        <div style="text-align:center; color:#94a3b8; padding:40px 0;">
                            <i class="far fa-calendar-check" style="font-size:40px; margin-bottom:10px;"></i>
                            <p>Clique em um dia no calendário para ver os detalhes das consultas.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ABA AGENDA (FILA DE ATENDIMENTO DETALHADA) -->
            <section id="aba-agenda" class="aba-conteudo" style="display:none;">
                <header class="page-header">
                    <h2>Fila e Triagem de Consultas</h2>
                    <p class="text-muted">Gerenciamento de solicitações com botões de ação rápida</p>
                </header>
                <div class="card" style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <select id="filtroMedicoAgenda" class="form-control" style="max-width: 400px;">
                            <option value="">Selecione o médico e a unidade...</option>
                            <?php foreach($medicos as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= $m['nome'] ?> - <?= $m['especialidade'] ?> (<?= $m['unidade_nome'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" id="filtroDataAgenda" class="form-control" style="max-width: 180px;">
                        <button class="btn btn-primary" onclick="renderizarAgenda()"><i class="fas fa-search"></i> Buscar Fila do Dia</button>
                    </div>
                </div>
                <div class="list-container" id="listaAgendaCompleta">
                    <p class="text-muted">Selecione o médico e a data para visualizar a fila de pacientes.</p>
                </div>
            </section>

            <!-- ABA PACIENTES -->
            <section id="aba-pacientes" class="aba-conteudo">
                <header class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div><h2>Base Global de Pacientes</h2></div>
                    <button class="btn btn-primary" onclick="document.getElementById('modalNovoPaciente').style.display='flex'"><i class="fas fa-plus"></i> Novo Paciente (Manual)</button>
                </header>

                <h3 style="color:#b45309; margin: 20px 0 10px 0; font-size:16px;"><i class="fas fa-user-clock"></i> Solicitações de Cadastro</h3>
                <div class="list-container" style="margin-bottom: 30px;">
                    <?php 
                    $tem_pendente = false;
                    foreach($pacientes as $p): 
                        $status = isset($p['status']) ? $p['status'] : 'Aprovado';
                        if($status == 'Pendente'): $tem_pendente = true;
                    ?>
                        <div class="card" style="border-left: 5px solid #f59e0b; margin-bottom:10px; background:#fffbeb;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <h4 style="margin-bottom:3px; color:#92400e;"><i class="fas fa-user-plus"></i> <?= $p['nome'] ?></h4>
                                    <p style="font-size:13px; color:#92400e; margin:0;">CPF: <b><?= $p['cpf'] ?></b> | SUS: <b><?= $p['sus'] ?></b> | Tel: <?= $p['telefone'] ?></p>
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <button class="btn btn-primary" style="background:#10b981; border:none; padding:8px 15px;" onclick="aprovarPaciente(<?= $p['id'] ?>)"><i class="fas fa-check"></i> Aceitar</button>
                                    <button class="btn btn-primary" style="background:#ef4444; border:none; padding:8px 15px;" onclick="rejeitarPaciente(<?= $p['id'] ?>)"><i class="fas fa-times"></i> Rejeitar</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                    <?php if(!$tem_pendente): ?> <p class="text-muted" style="font-size:13px;">Nenhuma solicitação nova no momento.</p> <?php endif; ?>
                </div>

                <h3 style="color:#1e293b; margin: 0 0 10px 0; font-size:16px;"><i class="fas fa-users"></i> Pacientes Ativos</h3>
                <div class="list-container">
                    <?php 
                    $tem_ativo = false;
                    foreach($pacientes as $p): 
                        $status = isset($p['status']) ? $p['status'] : 'Aprovado';
                        if($status == 'Aprovado'): $tem_ativo = true;
                    ?>
                        <div class="card" style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <h4 style="margin-bottom:3px; color:#1e293b;"><i class="fas fa-user"></i> <?= $p['nome'] ?></h4>
                                <p style="font-size:13px; color:#64748b;">CPF: <?= $p['cpf'] ?> | SUS: <?= $p['sus'] ?> | Tel: <?= $p['telefone'] ?></p>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button class="btn btn-outline-blue" onclick="abrirProntuario(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="fas fa-notes-medical"></i> Prontuário</button>
                                <?php if($_SESSION['tipo_unidade'] == 'UBS'): ?>
                                    <button class="btn btn-primary" onclick="abrirEncaminhamento(<?= $p['id'] ?>, '<?= $p['nome'] ?>')"><i class="fas fa-share"></i> Encaminhar (CEM)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                    <?php if(!$tem_ativo): ?> <p class="text-muted" style="font-size:13px;">Nenhum paciente aprovado na base.</p> <?php endif; ?>
                </div>
            </section>

            <!-- ABA PROFISSIONAIS (AGRUPADA POR MÉDICO PARA MÚLTIPLAS LOTAÇÕES) -->
            <section id="aba-medicos" class="aba-conteudo">
                <header class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div><h2>Profissionais de Saúde</h2><p class="text-muted">Gerenciamento de Médicos e suas Múltiplas Lotações (Agendas)</p></div>
                    <?php if($_SESSION['tipo_unidade'] == 'CENTRAL'): ?>
                        <button class="btn btn-primary" onclick="abrirModalMedicoNovo()"><i class="fas fa-user-plus"></i> Cadastrar Novo Médico</button>
                    <?php endif; ?>
                </header>
                
                <div style="display:flex; flex-direction:column; gap:20px;">
                    <?php foreach($medicos_agrupados as $crm => $grupo): ?>
                        <div class="card" style="border:1px solid #cbd5e1; box-shadow:none;">
                            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:15px; margin-bottom:15px; flex-wrap:wrap; gap:15px;">
                                <div style="display:flex; gap:15px; align-items:center;">
                                    <div style="width:45px; height:45px; background:#e0f2fe; color:#0284c7; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px;"><i class="fas fa-user-md"></i></div>
                                    <div>
                                        <h3 style="margin:0; font-size:16px; color:#0f172a;"><?= $grupo['nome'] ?></h3>
                                        <p style="margin:0; font-size:13px; color:#64748b;">CRM: <b><?= $crm ?></b></p>
                                    </div>
                                </div>
                                <?php if($_SESSION['tipo_unidade'] == 'CENTRAL'): ?>
                                    <button class="btn btn-outline-blue" onclick="abrirModalVincular('<?= htmlspecialchars($grupo['nome']) ?>', '<?= $crm ?>')"><i class="fas fa-plus-circle"></i> Adicionar à outra Unidade / UBS</button>
                                <?php endif; ?>
                            </div>
                            
                            <h4 style="font-size:13px; color:#475569; margin-bottom:10px; text-transform:uppercase;">Locais de Atendimento / Agendas:</h4>
                            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:15px;">
                                <?php foreach($grupo['vinculos'] as $v): ?>
                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:15px; position:relative;">
                                        <h5 style="margin:0 0 5px 0; color:#1e293b; font-size:14px;"><i class="fas fa-hospital"></i> <?= $v['unidade_nome'] ?></h5>
                                        <p style="margin:0 0 5px 0; font-size:13px; color:#059669; font-weight:bold;"><i class="fas fa-stethoscope"></i> <?= $v['especialidade'] ?></p>
                                        <p style="margin:0; font-size:13px; color:#475569;"><i class="far fa-clock"></i> Expediente: <?= $v['hora_inicio'] ?> às <?= $v['hora_fim'] ?></p>
                                        
                                        <?php if($_SESSION['tipo_unidade'] == 'CENTRAL'): ?>
                                            <div style="margin-top:12px; display:flex; gap:10px;">
                                                <button class="btn btn-outline-dark" style="padding:4px 8px; font-size:11px;" onclick="abrirModalEditarVinculo(<?= htmlspecialchars(json_encode($v)) ?>)"><i class="fas fa-edit"></i> Alterar Horário</button>
                                                <button class="btn btn-outline-red" style="padding:4px 8px; font-size:11px;" onclick="excluirMedico(<?= $v['id'] ?>)"><i class="fas fa-trash"></i> Remover Daqui</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($medicos_agrupados)): ?> <p class="text-muted">Nenhum médico cadastrado ou vinculado à sua unidade.</p> <?php endif; ?>
                </div>
            </section>

            <!-- ABA ENCAMINHAMENTOS -->
            <?php if($_SESSION['tipo_unidade'] == 'CENTRAL'): ?>
            <section id="aba-encaminhamentos" class="aba-conteudo">
                <header class="page-header"><h2>Fila de Regulação (CEM)</h2></header>
                <div class="list-container">
                    <?php foreach($encaminhamentos as $e): ?>
                        <div class="card" style="border-left: 5px solid #d97706; margin-bottom:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <h4 style="margin-bottom:5px;"><?= $e['paciente_nome'] ?> <span class="badge" style="background:#fef08a; color:#b45309;"><?= $e['status'] ?></span></h4>
                                    <p style="font-size:13px;">Origem: <b><?= $e['ubs_nome'] ?></b> <i class="fas fa-arrow-right"></i> Solicita: <b><?= $e['especialidade_destino'] ?></b></p>
                                </div>
                                <button class="btn btn-primary" onclick="alert('Basta agendar esse paciente na aba Agenda de um especialista.')">Analisado</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- MODAL NOVO PACIENTE -->
    <div id="modalNovoPaciente" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:999;">
        <div style="background:white; padding:30px; border-radius:12px; width:100%; max-width:500px;">
            <h3 style="margin-bottom:15px;">Cadastrar Paciente</h3>
            <input type="text" id="cadPacNome" class="form-control" style="margin-bottom:10px;" placeholder="Nome Completo" required>
            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <input type="text" id="cadPacCpf" class="form-control" placeholder="CPF" required>
                <input type="text" id="cadPacSus" class="form-control" placeholder="Cartão SUS" required>
            </div>
            <input type="tel" id="cadPacTel" class="form-control" style="margin-bottom:20px;" placeholder="WhatsApp" required>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" style="background:#e2e8f0; color:#4a5568;" onclick="document.getElementById('modalNovoPaciente').style.display='none'">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarPaciente()">Salvar e Aprovar</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE LIGAÇÃO MÉDICO <-> UNIDADE (APENAS CENTRAL) -->
    <?php if($_SESSION['tipo_unidade'] == 'CENTRAL'): ?>
    <div id="modalNovoMedico" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:999;">
        <div style="background:white; padding:30px; border-radius:12px; width:100%; max-width:550px;">
            <h3 id="tituloModalMedico" style="margin-bottom:15px;"><i class="fas fa-user-md"></i> Configurar Lotação do Médico</h3>
            <p style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:20px;">Use este painel para alocar o médico em uma Unidade e definir seus horários.</p>
            
            <input type="hidden" id="cadMedId">
            <input type="hidden" id="cadMedCrmOriginal">
            
            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <div style="flex:2;">
                    <label style="font-size:12px; font-weight:bold; color:#4a5568;">Nome do Profissional:</label>
                    <input type="text" id="cadMedNome" class="form-control" placeholder="Nome Completo" required>
                </div>
                <div style="flex:1;">
                    <label style="font-size:12px; font-weight:bold; color:#4a5568;">CRM:</label>
                    <input type="text" id="cadMedCrm" class="form-control" placeholder="CRM" required>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label style="font-size:12px; font-weight:bold; color:#4a5568;">Especialidade Geral:</label>
                    <input type="text" id="cadMedEsp" class="form-control" placeholder="Ex: Cardiologia" required>
                    <p style="font-size:11px; color:#059669; margin-top:5px; margin-bottom:0;"><i class="fas fa-info-circle"></i> Na UBS, salvará auto como Clínico Geral.</p>
                </div>
            </div>

            <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:15px; border-radius:8px; margin-bottom:20px;">
                <label style="font-size:12px; font-weight:bold; color:#4a5568;">Local de Atendimento (Qual Unidade?):</label>
                <select id="cadMedUnidade" class="form-control" style="margin-bottom:15px;" onchange="verificarEspecialidade()">
                    <option value="">-- Selecione a Unidade / UBS --</option>
                    <?php foreach($unidades_todas as $u): ?>
                        <option value="<?= $u['id'] ?>" data-tipo="<?= $u['tipo'] ?>"><?= $u['nome'] ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#4a5568;">Chegada nesta Unidade:</label>
                        <input type="time" id="cadMedInicio" class="form-control" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#4a5568;">Saída desta Unidade:</label>
                        <input type="time" id="cadMedFim" class="form-control" required>
                    </div>
                </div>
            </div>
            
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" style="background:#e2e8f0; color:#4a5568;" onclick="document.getElementById('modalNovoMedico').style.display='none'">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarMedico()"><i class="fas fa-save"></i> Salvar Lotação</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="modalProntuario" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:999;">
        <div style="background:white; padding:30px; border-radius:12px; width:100%; max-width:600px;">
            <h3 style="margin-bottom:5px;">Prontuário Médico Digital</h3>
            <p id="prontNomePaciente" style="margin-bottom:20px; color:#2b78ce; font-weight:bold;"></p>
            <textarea id="prontTexto" class="form-control" style="height:250px; resize:none; margin-bottom:15px;" placeholder="Insira o histórico..."></textarea>
            <input type="hidden" id="prontPacienteId">
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" style="background:#e2e8f0; color:#4a5568;" onclick="document.getElementById('modalProntuario').style.display='none'">Fechar</button>
                <button class="btn btn-primary" onclick="salvarProntuario()">Salvar Histórico</button>
            </div>
        </div>
    </div>

    <div id="modalEnc" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:999;">
        <div style="background:white; padding:30px; border-radius:12px; width:100%; max-width:400px;">
            <h3 style="margin-bottom:15px;">Encaminhar para o CEM</h3>
            <p id="encNomePaciente" style="margin-bottom:15px; font-weight:bold;"></p>
            <select id="encEspecialidade" class="form-control" style="margin-bottom:20px;">
                <?php foreach($medicos_cem as $mcem): ?>
                    <option value="<?= htmlspecialchars($mcem['especialidade'] . ' - Dr(a). ' . $mcem['nome']) ?>"><?= htmlspecialchars($mcem['especialidade']) ?> - Dr(a). <?= htmlspecialchars($mcem['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="encPacienteId">
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn" style="background:#e2e8f0; color:#4a5568;" onclick="document.getElementById('modalEnc').style.display='none'">Cancelar</button>
                <button class="btn btn-primary" onclick="enviarEncaminhamento()">Enviar à Central</button>
            </div>
        </div>
    </div>

    <script>
        let consultasJS = <?= json_encode($consultas) ?? '[]' ?>;
        let medicosJS = <?= json_encode($medicos) ?? '[]' ?>; 

        consultasJS = consultasJS.map(c => { if(c.status === 'Solicitado') c.status = 'Aguardando'; return c; });

        function gerarHorariosDoMedico(inicio, fim) {
            let horarios = [];
            let [hIni, mIni] = (inicio || '07:00').split(':').map(Number);
            let [hFim, mFim] = (fim || '15:00').split(':').map(Number);
            let atual = new Date(2000, 0, 1, hIni, mIni);
            let limite = new Date(2000, 0, 1, hFim, mFim);
            
            while(atual <= limite) {
                let hr = atual.getHours().toString().padStart(2, '0');
                let min = atual.getMinutes().toString().padStart(2, '0');
                let str = `${hr}:${min}`;
                if(str !== '12:00' && str !== '12:30') { horarios.push(str); }
                atual.setMinutes(atual.getMinutes() + 30);
            }
            return horarios;
        }

        window.onload = () => { 
            const cmp = document.getElementById('filtroDataAgenda'); 
            if(cmp) cmp.value = new Date().toISOString().split('T')[0]; 
            renderizarMesCalendario(); // Inicia o motor do calendário
        };

        function mudarAba(idAba, elemento) {
            document.querySelectorAll('.aba-conteudo').forEach(aba => aba.style.display = 'none');
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            document.getElementById('aba-' + idAba).style.display = 'block';
            if(elemento) elemento.classList.add('active');
        }

        // ==========================================
        // MOTOR DO CALENDÁRIO INTERATIVO GERAL
        // ==========================================
        let dataCalendario = new Date();
        
        function renderizarMesCalendario() {
            const mes = dataCalendario.getMonth();
            const ano = dataCalendario.getFullYear();
            
            const mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            document.getElementById('calMesAno').textContent = `${mesesNomes[mes]} de ${ano}`;

            const primeiroDia = new Date(ano, mes, 1).getDay();
            const diasNoMes = new Date(ano, mes + 1, 0).getDate();

            let html = '';
            let diaAtual = 1;
            for(let i = 0; i < 6; i++) {
                html += '<tr>';
                for(let j = 0; j < 7; j++) {
                    if(i === 0 && j < primeiroDia) {
                        html += '<td style="background:#f8fafc; border:1px solid #e2e8f0;"></td>';
                    } else if (diaAtual <= diasNoMes) {
                        let mesStr = (mes+1).toString().padStart(2, '0');
                        let diaStr = diaAtual.toString().padStart(2, '0');
                        let dataISO = `${ano}-${mesStr}-${diaStr}`;
                        
                        const filtroUnidade = document.getElementById('filtroUnidadeCalendario') ? document.getElementById('filtroUnidadeCalendario').value : '';
                        let consultasDia = consultasJS.filter(c => c.data_agendamento === dataISO && c.status !== 'Cancelado');
                        if(filtroUnidade) consultasDia = consultasDia.filter(c => c.unidade_id == filtroUnidade);
                        
                        let aguardando = consultasDia.filter(c => c.status === 'Aguardando' || c.status === 'Lista de Espera' || c.status === 'Aguardando Confirmação').length;
                        let confirmados = consultasDia.filter(c => c.status === 'Confirmado').length;
                        let atendidos = consultasDia.filter(c => c.status === 'Atendido').length;

                        let badge = '';
                        if(consultasDia.length > 0) {
                            badge = `<div style="margin-top:5px; font-size:10px; display:flex; flex-direction:column; gap:3px;">`;
                            if(aguardando > 0) badge += `<span style="background:#fef08a; color:#b45309; padding:2px 4px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><i class="fas fa-clock"></i> ${aguardando} Pendentes</span>`;
                            if(confirmados > 0) badge += `<span style="background:#d1fae5; color:#065f46; padding:2px 4px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><i class="fas fa-check"></i> ${confirmados} Confir.</span>`;
                            if(atendidos > 0) badge += `<span style="background:#dbeafe; color:#1e40af; padding:2px 4px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><i class="fas fa-check-double"></i> ${atendidos} Atend.</span>`;
                            badge += `</div>`;
                        }

                        let bgHover = consultasDia.length > 0 ? '#f1f5f9' : '#fff';
                        html += `<td onclick="abrirDiaCalendario('${dataISO}')" style="cursor:pointer; vertical-align:top; height:90px; width:14%; border:1px solid #e2e8f0; padding:8px; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='${bgHover}'">
                                <strong style="color:#475569; font-size:14px;">${diaAtual}</strong>
                                ${badge}
                        </td>`;
                        diaAtual++;
                    } else {
                        html += '<td style="background:#f8fafc; border:1px solid #e2e8f0;"></td>';
                    }
                }
                html += '</tr>';
                if(diaAtual > diasNoMes) break;
            }
            document.getElementById('corpoCalendario').innerHTML = html;
        }

        function mudarMes(delta) {
            dataCalendario.setMonth(dataCalendario.getMonth() + delta);
            renderizarMesCalendario();
            document.getElementById('painelDiaCalendario').innerHTML = '<div style="text-align:center; color:#94a3b8; padding:40px 0;"><i class="far fa-calendar-check" style="font-size:40px; margin-bottom:10px;"></i><p>Clique em um dia no calendário para ver os detalhes das consultas.</p></div>';
        }

        function abrirDiaCalendario(dataISO) {
            const filtroUnidade = document.getElementById('filtroUnidadeCalendario') ? document.getElementById('filtroUnidadeCalendario').value : '';
            let consultasDia = consultasJS.filter(c => c.data_agendamento === dataISO && c.status !== 'Cancelado');
            if(filtroUnidade) consultasDia = consultasDia.filter(c => c.unidade_id == filtroUnidade);
            
            let partesData = dataISO.split('-');
            let dataBR = `${partesData[2]}/${partesData[1]}/${partesData[0]}`;
            
            let html = `<h3 style="margin-top:0; margin-bottom:15px; border-bottom:2px solid #e2e8f0; padding-bottom:10px; color:#1e293b;">Consultas: ${dataBR}</h3>`;
            
            if(consultasDia.length === 0) {
                html += `<div style="text-align:center; padding:30px 0;"><i class="fas fa-bed" style="font-size:30px; color:#cbd5e1; margin-bottom:10px;"></i><p class="text-muted">Agenda totalmente livre para este dia.</p></div>`;
            } else {
                html += `<div style="display:flex; flex-direction:column; gap:10px; max-height:500px; overflow-y:auto; padding-right:5px;">`;
                // Ordenar pelo horário
                consultasDia.sort((a,b) => a.hora.localeCompare(b.hora));
                
                consultasDia.forEach(c => {
                    let corStatus = '#94a3b8'; let bgStatus = '#f1f5f9';
                    if(c.status === 'Aguardando' || c.status === 'Aguardando Confirmação') { corStatus = '#d97706'; bgStatus = '#fef3c7'; }
                    if(c.status === 'Confirmado') { corStatus = '#059669'; bgStatus = '#d1fae5'; }
                    if(c.status === 'Atendido') { corStatus = '#2563eb'; bgStatus = '#dbeafe'; }
                    if(c.status === 'Lista de Espera') { corStatus = '#7c3aed'; bgStatus = '#ede9fe'; }
                    
                    html += `
                    <div style="border:1px solid #cbd5e1; border-left:4px solid ${corStatus}; border-radius:8px; padding:12px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <strong style="color:#1e293b; font-size:14px;"><i class="far fa-clock"></i> ${c.hora} - ${c.paciente_nome}</strong>
                            <span style="font-size:10px; font-weight:bold; color:${corStatus}; background:${bgStatus}; padding:2px 8px; border-radius:12px;">${c.status}</span>
                        </div>
                        <div style="font-size:12px; color:#64748b; margin-bottom:10px; line-height:1.4;">
                            <div><i class="fas fa-hospital"></i> <b>Local:</b> ${c.unidade_nome}</div>
                            <div><i class="fas fa-user-md"></i> <b>Médico:</b> Dr(a). ${c.medico_nome} (${c.especialidade})</div>
                        </div>
                        <button class="btn btn-outline-dark" style="width:100%; padding:6px; font-size:12px;" onclick="irParaAgenda('${dataISO}', ${c.medico_id})">Gerenciar na Fila <i class="fas fa-arrow-right"></i></button>
                    </div>`;
                });
                html += `</div>`;
            }
            document.getElementById('painelDiaCalendario').innerHTML = html;
        }

        // Função de atalho que leva do calendário direto para a fila configurada
        function irParaAgenda(dataISO, medico_id) {
            mudarAba('agenda', document.getElementById('nav-agenda'));
            document.getElementById('filtroDataAgenda').value = dataISO;
            document.getElementById('filtroMedicoAgenda').value = medico_id;
            renderizarAgenda();
        }

        // ==========================================
        // OUTRAS FUNÇÕES DO SISTEMA (CRUD, APROVAÇÕES)
        // ==========================================
        function salvarPaciente() {
            const dados = { nome: document.getElementById('cadPacNome').value, cpf: document.getElementById('cadPacCpf').value, sus: document.getElementById('cadPacSus').value, telefone: document.getElementById('cadPacTel').value };
            fetch('api.php?action=salvar_paciente', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(dados) })
            .then(r => r.json()).then(d => { if(d.sucesso) { alert("Salvo!"); window.location.reload(); } else alert(d.msg); });
        }

        function aprovarPaciente(id) { if(confirm("Deseja APROVAR a entrada deste paciente?")) fetch('api.php?action=aprovar_paciente', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id: id}) }).then(r=>r.json()).then(d=>{ window.location.reload(); }); }
        function rejeitarPaciente(id) { if(confirm("Deseja REJEITAR este cadastro?")) fetch('api.php?action=rejeitar_paciente', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id: id}) }).then(r=>r.json()).then(d=>{ window.location.reload(); }); }

        function verificarEspecialidade() {
            const select = document.getElementById('cadMedUnidade');
            if(select.selectedIndex === -1 || select.value === "") return;
            const tipo = select.options[select.selectedIndex].getAttribute('data-tipo');
            const inputEsp = document.getElementById('cadMedEsp');
            
            if(tipo !== 'CENTRAL') {
                inputEsp.value = 'Clínico Geral'; inputEsp.readOnly = true; inputEsp.style.backgroundColor = '#f1f5f9';
            } else {
                if(inputEsp.value === 'Clínico Geral') inputEsp.value = '';
                inputEsp.readOnly = false; inputEsp.style.backgroundColor = '#fff';
            }
        }

        function abrirModalMedicoNovo() {
            document.getElementById('tituloModalMedico').innerHTML = "<i class='fas fa-user-plus'></i> Cadastrar Médico Totalmente Novo";
            document.getElementById('cadMedId').value = ''; document.getElementById('cadMedCrmOriginal').value = '';
            document.getElementById('cadMedNome').value = ''; document.getElementById('cadMedCrm').value = '';
            document.getElementById('cadMedEsp').value = ''; document.getElementById('cadMedUnidade').value = '';
            document.getElementById('cadMedInicio').value = '07:00'; document.getElementById('cadMedFim').value = '15:00';
            document.getElementById('cadMedNome').readOnly = false; document.getElementById('cadMedCrm').readOnly = false;
            verificarEspecialidade();
            document.getElementById('modalNovoMedico').style.display = 'flex';
        }

        function abrirModalVincular(nome, crm) {
            document.getElementById('tituloModalMedico').innerHTML = "<i class='fas fa-plus-circle'></i> Adicionar Local de Atendimento";
            document.getElementById('cadMedId').value = ''; document.getElementById('cadMedCrmOriginal').value = crm;
            document.getElementById('cadMedNome').value = nome; document.getElementById('cadMedCrm').value = crm;
            document.getElementById('cadMedEsp').value = ''; document.getElementById('cadMedUnidade').value = '';
            document.getElementById('cadMedInicio').value = '07:00'; document.getElementById('cadMedFim').value = '15:00';
            document.getElementById('cadMedNome').readOnly = true; document.getElementById('cadMedCrm').readOnly = true; 
            verificarEspecialidade();
            document.getElementById('modalNovoMedico').style.display = 'flex';
        }

        function abrirModalEditarVinculo(vinculo) {
            document.getElementById('tituloModalMedico').innerHTML = "<i class='fas fa-edit'></i> Alterar Horário / Lotação";
            document.getElementById('cadMedId').value = vinculo.id; document.getElementById('cadMedCrmOriginal').value = vinculo.crm;
            document.getElementById('cadMedNome').value = vinculo.nome; document.getElementById('cadMedCrm').value = vinculo.crm;
            document.getElementById('cadMedEsp').value = vinculo.especialidade; document.getElementById('cadMedUnidade').value = vinculo.unidade_id;
            document.getElementById('cadMedInicio').value = vinculo.hora_inicio || '07:00'; document.getElementById('cadMedFim').value = vinculo.hora_fim || '15:00';
            document.getElementById('cadMedNome').readOnly = false; document.getElementById('cadMedCrm').readOnly = false;
            verificarEspecialidade();
            document.getElementById('modalNovoMedico').style.display = 'flex';
        }

        function salvarMedico() {
            const dados = { 
                id: document.getElementById('cadMedId').value, crm_original: document.getElementById('cadMedCrmOriginal').value,
                nome: document.getElementById('cadMedNome').value, crm: document.getElementById('cadMedCrm').value, 
                especialidade: document.getElementById('cadMedEsp').value, unidade_id: document.getElementById('cadMedUnidade').value,
                hora_inicio: document.getElementById('cadMedInicio').value, hora_fim: document.getElementById('cadMedFim').value
            };
            if(!dados.nome || !dados.crm || !dados.unidade_id || !dados.especialidade) { alert("Preencha todos os campos corretamente."); return; }
            
            fetch('api.php?action=salvar_medico', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(dados) })
            .then(r => r.json()).then(d => { if(d.sucesso) { alert("Configurações do médico salvas com sucesso!"); window.location.reload(); } else { alert("ERRO DE SISTEMA: " + d.msg); } })
            .catch(e => alert("Falha na comunicação com o banco de dados."));
        }

        function excluirMedico(id) { if(confirm("Deseja remover este médico desta unidade?")) { fetch('api.php?action=excluir_medico', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id: id}) }).then(r => r.json()).then(d => { if(d.sucesso) window.location.reload(); }); } }

        function abrirProntuario(paciente) { document.getElementById('prontPacienteId').value = paciente.id; document.getElementById('prontNomePaciente').textContent = "Paciente: " + paciente.nome; document.getElementById('prontTexto').value = paciente.prontuario || ''; document.getElementById('modalProntuario').style.display = 'flex'; }
        function salvarProntuario() { fetch('api.php?action=salvar_prontuario', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({paciente_id: document.getElementById('prontPacienteId').value, prontuario: document.getElementById('prontTexto').value}) }).then(r=>r.json()).then(d=> { alert("Prontuário salvo!"); window.location.reload(); }); }
        function abrirEncaminhamento(id, nome) { document.getElementById('encPacienteId').value = id; document.getElementById('encNomePaciente').textContent = nome; document.getElementById('modalEnc').style.display = 'flex'; }
        function enviarEncaminhamento() { const id = document.getElementById('encPacienteId').value; const esp = document.getElementById('encEspecialidade').value; if(!esp) { alert('Selecione!'); return; } fetch('api.php?action=encaminhar', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({paciente_id: id, especialidade: esp}) }).then(r=>r.json()).then(d=> { alert("Enviado à CEM!"); document.getElementById('modalEnc').style.display='none'; }); }

        function renderizarAgenda() {
            const data = document.getElementById('filtroDataAgenda').value; const medico_id = document.getElementById('filtroMedicoAgenda').value; const lista = document.getElementById('listaAgendaCompleta'); lista.innerHTML = '';
            if(!data || !medico_id) { lista.innerHTML = '<p class="text-muted">Selecione a data, unidade e médico para ver a grade.</p>'; return; }

            const medSelecionado = medicosJS.find(m => m.id == medico_id);
            const HORARIOS_DINAMICOS = gerarHorariosDoMedico(medSelecionado.hora_inicio, medSelecionado.hora_fim);

            const consultasDoDia = consultasJS.filter(c => c.data_agendamento === data && c.medico_id == medico_id);
            const espera = consultasDoDia.filter(c => c.status === 'Lista de Espera'); const qtdEspera = espera.length;

            let htmlGrid = '<div style="display:flex; flex-direction:column; gap:2px;">';

            HORARIOS_DINAMICOS.forEach(hora => {
                const c = consultasDoDia.find(cons => cons.hora === hora && cons.status !== 'Lista de Espera');
                if (c) {
                    let classCard = 'status-aguardando'; let classBadge = 'bg-badge-aguardando'; let textoBadge = c.status; let botoes = '';
                    if(c.status === 'Aguardando' || c.status === 'Aguardando Confirmação') { classCard = 'status-aguardando'; classBadge = 'bg-badge-aguardando'; textoBadge = 'Aguardando'; botoes = `<button class="btn-acao btn-outline-green" onclick="alterarStatusConsulta(${c.id}, 'Confirmado', '${data}', ${medico_id})"><i class="far fa-check-circle"></i> Confirmar</button> <button class="btn-acao btn-outline-red" onclick="alterarStatusConsulta(${c.id}, 'Cancelado', '${data}', ${medico_id})"><i class="far fa-times-circle"></i> Cancelar</button> <button class="btn-acao btn-outline-purple" onclick="alterarStatusConsulta(${c.id}, 'Lista de Espera', '${data}', ${medico_id})"><i class="fas fa-list"></i> Desistência</button> <button class="btn-acao btn-outline-dark" onclick="abrirWhatsApp('${c.telefone}', '${c.paciente_nome}', '${data}', '${hora}', 'confirmacao')"><i class="fab fa-whatsapp"></i> WhatsApp</button>`; } else if(c.status === 'Confirmado') { classCard = 'status-confirmado'; classBadge = 'bg-badge-confirmado'; textoBadge = 'Confirmado'; botoes = `<button class="btn-acao btn-outline-green" onclick="alterarStatusConsulta(${c.id}, 'Atendido', '${data}', ${medico_id})"><i class="fas fa-user-check"></i> Atendido</button> <button class="btn-acao btn-outline-dark" onclick="alterarStatusConsulta(${c.id}, 'Aguardando', '${data}', ${medico_id})"><i class="fas fa-undo"></i> Desfazer</button> <button class="btn-acao btn-outline-dark" onclick="abrirWhatsApp('${c.telefone}', '${c.paciente_nome}', '${data}', '${hora}', 'lembrete')"><i class="fab fa-whatsapp"></i> WhatsApp ✔</button>`; } else if(c.status === 'Cancelado') { classCard = 'status-cancelado'; classBadge = 'bg-badge-cancelado'; textoBadge = 'Cancelado'; botoes = `<button class="btn-acao btn-outline-dark" onclick="alterarStatusConsulta(${c.id}, 'Aguardando', '${data}', ${medico_id})"><i class="fas fa-undo"></i> Desfazer Cancelamento</button>`; } else if(c.status === 'Atendido') { classCard = 'status-atendido'; classBadge = 'bg-badge-atendido'; textoBadge = 'Atendido'; botoes = `<span style="color:#059669; font-size:13px; font-weight:bold;"><i class="fas fa-check-double"></i> Paciente Atendido</span>`; }
                    htmlGrid += `<div class="card-agenda ${classCard}"> <div class="agenda-header"> <div class="agenda-info-paciente"> <h4><span class="hora-badge">${hora}</span> ${c.paciente_nome}</h4> <p>Tel: ${c.telefone} | CPF: ${c.cpf}</p> </div> <span class="badge-status-top ${classBadge}">${textoBadge}</span> </div> <div class="agenda-actions">${botoes}</div> </div>`;
                } else { htmlGrid += `<div class="card-agenda" style="border-left: 2px dashed #cbd5e1; background: #f8fafc; padding:15px; box-shadow:none;"> <p style="margin:0; color:#94a3b8; font-size:14px;"><span class="hora-badge" style="background:#f1f5f9;">${hora}</span> Horário Vago (Livre para Encaixe)</p> </div>`; }
            });

            htmlGrid += '</div>'; lista.innerHTML += htmlGrid;
            let esperaHtml = `<div style="margin-top:40px; padding:20px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:12px;"> <h3 style="margin:0 0 15px 0; color:#6b21a8; font-size:16px; display:flex; justify-content:space-between;"> <span><i class="fas fa-list-ol"></i> Fila de Desistência (Espera)</span> <span style="background:#e9d5ff; padding:2px 8px; border-radius:10px; font-size:12px;">${qtdEspera}/5 vagas</span> </h3>`;
            if(qtdEspera === 0) { esperaHtml += `<p class="text-muted" style="margin:0; font-size:13px;">Nenhum paciente na fila de espera para este dia.</p>`; } else {
                esperaHtml += '<div style="display:flex; flex-direction:column; gap:10px;">';
                espera.forEach((c, index) => { esperaHtml += `<div class="card-agenda status-espera" style="padding:15px; margin:0;"> <div class="agenda-header"> <div class="agenda-info-paciente"> <h4><span class="hora-badge" style="background:#ede9fe; color:#5b21b6;">#${index+1}</span> ${c.paciente_nome}</h4> <p>Tel: ${c.telefone} | Queria às: ${c.hora}</p> </div> <span class="badge-status-top bg-badge-espera">Na Espera</span> </div> <div class="agenda-actions"> <button class="btn-acao btn-outline-green" onclick="alterarStatusConsulta(${c.id}, 'Confirmado', '${data}', ${medico_id})"><i class="far fa-check-circle"></i> Aprovar (Dar Vaga)</button> <button class="btn-acao btn-outline-red" onclick="alterarStatusConsulta(${c.id}, 'Cancelado', '${data}', ${medico_id})"><i class="far fa-times-circle"></i> Retirar</button> <button class="btn-acao btn-outline-dark" onclick="abrirWhatsApp('${c.telefone}', '${c.paciente_nome}', '${data}', '${c.hora}', 'espera')"><i class="fab fa-whatsapp"></i> Avisar Vaga</button> </div> </div>`; });
                esperaHtml += '</div>';
            }
            esperaHtml += `</div>`; lista.innerHTML += esperaHtml;
        }

        function abrirWhatsApp(telefone, nome, data, hora, tipo) {
            let num = telefone.replace(/\D/g, ''); if(num.length < 10) { alert('Telefone inválido para WhatsApp.'); return; }
            let partesData = data.split('-'); let dataBR = `${partesData[2]}/${partesData[1]}/${partesData[0]}`; let msg = '';
            if(tipo === 'confirmacao') { msg = `Olá *${nome}*, aqui é da Secretaria de Saúde de Porto Ferreira.\n\nRecebemos seu pedido de consulta para o dia *${dataBR}*. Por favor, responda esta mensagem para *CONFIRMAR* ou *CANCELAR* sua presença.`; } else if(tipo === 'espera') { msg = `Olá *${nome}*, aqui é da Secretaria de Saúde!\n\nVocê estava na nossa lista de espera e *ABRIU UMA VAGA DE DESISTÊNCIA* para consulta no dia *${dataBR}*.\n\nVocê tem interesse em assumir essa vaga?`; } else if(tipo === 'lembrete') { msg = `Olá *${nome}*, passando para lembrar que sua consulta está *CONFIRMADA* para o dia *${dataBR} às ${hora}*.`; }
            window.open(`https://wa.me/55${num}?text=${encodeURIComponent(msg)}`, '_blank');
        }

        function alterarStatusConsulta(id, novoStatus, dataConsulta, medicoId) {
            if(novoStatus === 'Lista de Espera') { const filaAtual = consultasJS.filter(c => c.data_agendamento === dataConsulta && c.medico_id == medicoId && c.status === 'Lista de Espera' && c.id !== id); if(filaAtual.length >= 5) { alert('⚠️ ATENÇÃO: A Lista de Espera/Desistência para este dia já atingiu o limite de 5 pacientes!'); return; } }
            fetch('api.php?action=mudar_status', { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id: id, status: novoStatus}) }).then(r => r.json()).then(d => { if(d.sucesso) { window.location.reload(); } });
        }
    </script>
</body>
</html>
