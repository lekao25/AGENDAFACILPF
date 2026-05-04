<?php
session_start();
require 'conexao.php';
header('Content-Type: application/json');

// Correção: Tenta criar cada coluna separadamente para evitar travamentos do Banco de Dados
$atualizacoes_banco = [
    "ALTER TABLE pacientes ADD COLUMN status VARCHAR(20) DEFAULT 'Aprovado'",
    "ALTER TABLE medicos ADD COLUMN hora_inicio VARCHAR(5) DEFAULT '07:00'",
    "ALTER TABLE medicos ADD COLUMN hora_fim VARCHAR(5) DEFAULT '15:00'"
];
foreach($atualizacoes_banco as $sql) {
    try { $pdo->exec($sql); } catch (Exception $e) {}
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

// ============================================
// REQUISIÇÕES DO PORTAL DO PACIENTE (INDEX)
// ============================================
if ($action == 'verificar_cpf') {
    $stmt = $pdo->prepare("SELECT id, nome, status FROM pacientes WHERE cpf = ?");
    $stmt->execute([$data['cpf']]);
    $pac = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pac) { echo json_encode(['status' => 'novo']); }
    else if ($pac['status'] == 'Pendente') { echo json_encode(['status' => 'pendente']); }
    else { echo json_encode(['status' => 'aprovado', 'paciente' => $pac]); }
    exit;
}

if ($action == 'solicitar_cadastro') {
    $stmt = $pdo->prepare("SELECT id FROM pacientes WHERE sus = ?");
    $stmt->execute([$data['sus']]);
    if ($stmt->fetch()) { echo json_encode(['sucesso' => false, 'msg' => '⚠️ Este Cartão SUS já pertence a outro paciente cadastrado.']); exit; }
    
    $stmt = $pdo->prepare("INSERT INTO pacientes (nome, cpf, sus, telefone, status) VALUES (?, ?, ?, ?, 'Pendente')");
    $stmt->execute([$data['nome'], $data['cpf'], $data['sus'], $data['telefone']]);
    echo json_encode(['sucesso' => true]); exit;
}

if ($action == 'horarios_disponiveis') {
    $stmt = $pdo->prepare("SELECT hora_inicio, hora_fim FROM medicos WHERE id = ?");
    $stmt->execute([$data['medico_id']]);
    $med = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $h_ini = !empty($med['hora_inicio']) ? $med['hora_inicio'] : '07:00';
    $h_fim = !empty($med['hora_fim']) ? $med['hora_fim'] : '15:00';
    
    $inicio = strtotime($h_ini);
    $fim = strtotime($h_fim);
    $horarios_todos = [];
    while ($inicio <= $fim) {
        $str = date('H:i', $inicio);
        if ($str !== '12:00' && $str !== '12:30') {
            $horarios_todos[] = $str;
        }
        $inicio += 30 * 60;
    }
    
    $stmt = $pdo->prepare("SELECT hora FROM consultas WHERE medico_id = ? AND data_agendamento = ? AND status IN ('Aguardando', 'Confirmado', 'Atendido')");
    $stmt->execute([$data['medico_id'], $data['data']]);
    $ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $disponiveis = array_values(array_diff($horarios_todos, $ocupados));
    echo json_encode($disponiveis); exit;
}

if ($action == 'agendar') {
    $stmt = $pdo->prepare("SELECT id FROM consultas WHERE medico_id = ? AND data_agendamento = ? AND hora = ? AND status IN ('Aguardando', 'Confirmado', 'Atendido')");
    $stmt->execute([$data['medico_id'], $data['data'], $data['hora']]);
    if ($stmt->fetch()) {
        echo json_encode(['sucesso' => false, 'msg' => '🚫 Este horário acabou de ser preenchido por outra pessoa. Por favor, escolha outro.']); exit;
    }

    $stmt = $pdo->prepare("SELECT unidade_id FROM medicos WHERE id = ?");
    $stmt->execute([$data['medico_id']]);
    $med = $stmt->fetch();
    
    $stmt = $pdo->prepare("INSERT INTO consultas (paciente_id, medico_id, unidade_id, data_agendamento, hora, status) VALUES (?, ?, ?, ?, ?, 'Aguardando')");
    $stmt->execute([$data['paciente_id'], $data['medico_id'], $med['unidade_id'], $data['data'], $data['hora']]);
    echo json_encode(['sucesso' => true]); exit;
}

// ============================================
// REQUISIÇÕES DA SECRETARIA (ADMIN)
// ============================================
if ($action == 'mudar_status') {
    $stmt = $pdo->prepare("UPDATE consultas SET status = ? WHERE id = ?");
    $stmt->execute([$data['status'], $data['id']]);
    echo json_encode(['sucesso' => true]); exit;
}

if ($action == 'salvar_paciente') {
    $stmt = $pdo->prepare("SELECT id FROM pacientes WHERE cpf = ? OR sus = ?");
    $stmt->execute([$data['cpf'], $data['sus']]);
    if ($stmt->fetch()) { echo json_encode(['sucesso' => false, 'msg' => "O CPF ou SUS já está cadastrado no sistema."]); exit; }
    
    $stmt = $pdo->prepare("INSERT INTO pacientes (nome, cpf, sus, telefone, status) VALUES (?, ?, ?, ?, 'Aprovado')");
    $stmt->execute([$data['nome'], $data['cpf'], $data['sus'], $data['telefone']]);
    echo json_encode(['sucesso' => true, 'msg' => 'Paciente salvo e ativo na rede!']); exit;
}

if ($action == 'aprovar_paciente') {
    $stmt = $pdo->prepare("UPDATE pacientes SET status = 'Aprovado' WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['sucesso' => true]); exit;
}

if ($action == 'rejeitar_paciente') {
    $stmt = $pdo->prepare("DELETE FROM pacientes WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['sucesso' => true]); exit;
}

if ($action == 'salvar_medico') {
    try {
        if (empty($data['id'])) {
            $stmt = $pdo->prepare("INSERT INTO medicos (nome, crm, especialidade, unidade_id, hora_inicio, hora_fim) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['nome'], $data['crm'], $data['especialidade'], $data['unidade_id'], $data['hora_inicio'], $data['hora_fim']]);
        } else {
            $stmt = $pdo->prepare("UPDATE medicos SET nome=?, crm=?, especialidade=?, unidade_id=?, hora_inicio=?, hora_fim=? WHERE id=?");
            $stmt->execute([$data['nome'], $data['crm'], $data['especialidade'], $data['unidade_id'], $data['hora_inicio'], $data['hora_fim'], $data['id']]);
        }
        echo json_encode(['sucesso' => true]); 
    } catch (PDOException $e) {
        echo json_encode(['sucesso' => false, 'msg' => 'Erro interno do BD: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'excluir_medico') {
    $stmt = $pdo->prepare("DELETE FROM medicos WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['sucesso' => true]); exit;
}

if ($action == 'salvar_prontuario') {
    $stmt = $pdo->prepare("UPDATE pacientes SET prontuario = ? WHERE id = ?");
    $stmt->execute([$data['prontuario'], $data['paciente_id']]);
    echo json_encode(['sucesso' => true]); exit;
}

if ($action == 'encaminhar') {
    $stmt = $pdo->prepare("INSERT INTO encaminhamentos (paciente_id, ubs_id, especialidade_destino, status) VALUES (?, ?, ?, 'Pendente')");
    $stmt->execute([$data['paciente_id'], $_SESSION['unidade_id'], $data['especialidade']]);
    echo json_encode(['sucesso' => true]); exit;
}
?>
