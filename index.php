<?php
require 'conexao.php';
// Busca os médicos para preencher o formulário caso o paciente seja aprovado
$medicos = $pdo->query("SELECT m.id, m.nome, m.especialidade, u.nome as unidade FROM medicos m JOIN unidades u ON m.unidade_id = u.id ORDER BY u.nome, m.nome")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Portal do Paciente - Agendamento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-image: url('fundo-medicos.jpg'); background-size: cover; background-position: center; background-attachment: fixed; background-color: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 30, 60, 0.7); z-index: 1; }
        .container { position: relative; z-index: 2; background: rgba(255,255,255,0.95); padding: 40px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 15px 35px rgba(0,0,0,0.3); backdrop-filter: blur(10px); }
        .header { text-align: center; margin-bottom: 30px; }
        .header h2 { color: #1e293b; margin: 0 0 10px 0; }
        .header p { color: #64748b; margin: 0; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #475569; font-size: 13px; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; transition: border-color 0.2s; }
        .form-control:focus { border-color: #3b82f6; outline: none; }
        .btn { width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
        .btn-primary { background-color: #1e3a8a; color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-success:hover { opacity: 0.9; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .link-voltar { display: block; text-align: center; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 13px; cursor: pointer; }
        .link-voltar:hover { text-decoration: underline; }
        .admin-link { display: block; text-align: center; margin-top: 20px; color: #94a3b8; text-decoration: none; font-size: 12px; }
    </style>
</head>
<body>
    <div class="overlay"></div>
    <div class="container">
        <div class="header">
            <i class="fas fa-heartbeat" style="font-size: 40px; color: #1e3a8a; margin-bottom: 10px;"></i>
            <h2>Portal do Paciente</h2>
            <p>Acesse com seu CPF para Agendar Consultas</p>
        </div>

        <!-- PASSO 1: IDENTIFICAÇÃO -->
        <div id="step-identificacao">
            <div class="form-group">
                <label>Digite seu CPF (Apenas números):</label>
                <input type="text" id="cpfBusca" class="form-control" maxlength="11" placeholder="Ex: 12345678900" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>
            <button class="btn btn-primary" onclick="verificarCpf()">Entrar no Portal <i class="fas fa-arrow-right"></i></button>
            <a href="admin.php" class="admin-link"><i class="fas fa-lock"></i> Acesso Restrito a Servidores</a>
        </div>

        <!-- PASSO 2: CADASTRO (CASO SEJA NOVO) -->
        <div id="step-cadastro" style="display:none;">
            <div class="alert" style="background:#e0f2fe; color:#075985; border:1px solid #bae6fd;">
                <i class="fas fa-info-circle"></i> <b>Primeiro Acesso:</b> Preencha seus dados. O cadastro passará por aprovação da Unidade de Saúde para liberar o agendamento.
            </div>
            <div class="form-group"><label>Nome Completo:</label><input type="text" id="cadNome" class="form-control"></div>
            <div class="form-group"><label>Cartão SUS:</label><input type="text" id="cadSus" class="form-control" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
            <div class="form-group"><label>Telefone (WhatsApp):</label><input type="text" id="cadTel" class="form-control" placeholder="Ex: 19999999999"></div>
            <button class="btn btn-success" onclick="solicitarCadastro()">Enviar Solicitação de Cadastro</button>
            <a class="link-voltar" onclick="voltarInicio()"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <!-- PASSO 3: MENSAGEM DE AGUARDANDO APROVAÇÃO -->
        <div id="step-pendente" style="display:none;">
            <div class="alert" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; text-align:center;">
                <i class="fas fa-clock" style="font-size:30px; margin-bottom:15px; display:block;"></i>
                <h3 style="margin:0 0 10px 0;">Cadastro em Análise</h3>
                Sua solicitação de cadastro foi recebida e está aguardando aprovação da Secretaria.<br><br>
                <b>Você poderá acessar a agenda assim que seu cadastro for aprovado.</b>
            </div>
            <a class="link-voltar" onclick="voltarInicio()"><i class="fas fa-arrow-left"></i> Voltar ao Início</a>
        </div>

        <!-- PASSO 4: AGENDAMENTO (SÓ PARA PACIENTES APROVADOS) -->
        <div id="step-agendamento" style="display:none;">
            <div class="alert" style="background:#d1fae5; color:#065f46; border:1px solid #a7f3d0;">
                <i class="fas fa-user-check"></i> Bem-vindo(a), <b id="nomePacienteLogado"></b>!
            </div>
            <input type="hidden" id="pacienteIdLogado">
            
            <div class="form-group">
                <label>Médico / Especialidade:</label>
                <select id="agenMedico" class="form-control" onchange="buscarHorarios()">
                    <option value="">Selecione o profissional...</option>
                    <?php foreach($medicos as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= $m['nome'] ?> - <?= $m['especialidade'] ?> (<?= $m['unidade'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Data Desejada:</label>
                    <input type="date" id="agenData" class="form-control" onchange="buscarHorarios()">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Horário Livre:</label>
                    <select id="agenHora" class="form-control">
                        <option value="">Selecione a data...</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="agendarConsulta()"><i class="far fa-calendar-check"></i> Confirmar Agendamento</button>
            <a class="link-voltar" onclick="voltarInicio()"><i class="fas fa-sign-out-alt"></i> Sair da Conta</a>
        </div>

    </div>

    <script>
        let pacienteCpfAtual = '';

        // Definir a data mínima do input date para Hoje
        document.getElementById('agenData').min = new Date().toISOString().split('T')[0];

        // FUNÇÃO MATEMÁTICA OFICIAL PARA VALIDAR CPF REAL
        function validarCPF(cpf) {
            cpf = cpf.replace(/[^\d]+/g,'');
            if(cpf == '') return false;
            
            // Elimina CPFs inválidos conhecidos ou com tamanho errado
            if (cpf.length != 11 || 
                cpf == "00000000000" || cpf == "11111111111" || 
                cpf == "22222222222" || cpf == "33333333333" || 
                cpf == "44444444444" || cpf == "55555555555" || 
                cpf == "66666666666" || cpf == "77777777777" || 
                cpf == "88888888888" || cpf == "99999999999")
                    return false;
                    
            // Valida 1o dígito
            let add = 0;
            for (let i=0; i < 9; i ++) add += parseInt(cpf.charAt(i)) * (10 - i);
            let rev = 11 - (add % 11);
            if (rev == 10 || rev == 11) rev = 0;
            if (rev != parseInt(cpf.charAt(9))) return false;
            
            // Valida 2o dígito
            add = 0;
            for (let i = 0; i < 10; i ++) add += parseInt(cpf.charAt(i)) * (11 - i);
            rev = 11 - (add % 11);
            if (rev == 10 || rev == 11) rev = 0;
            if (rev != parseInt(cpf.charAt(10))) return false;
            
            return true;
        }

        function verificarCpf() {
            const cpf = document.getElementById('cpfBusca').value.trim();
            
            if(!cpf) { alert("Por favor, digite o CPF!"); return; }
            
            // CHAMA A VALIDAÇÃO AQUI ANTES DE IR PRO BANCO DE DADOS
            if(!validarCPF(cpf)) {
                alert("❌ CPF INVÁLIDO!\nPor favor, digite um número de CPF verdadeiro.");
                document.getElementById('cpfBusca').value = '';
                document.getElementById('cpfBusca').focus();
                return;
            }
            
            fetch('api.php?action=verificar_cpf', {
                method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({cpf: cpf})
            }).then(r=>r.json()).then(d => {
                pacienteCpfAtual = cpf;
                document.getElementById('step-identificacao').style.display = 'none';
                
                if(d.status === 'novo') {
                    document.getElementById('step-cadastro').style.display = 'block';
                } else if(d.status === 'pendente') {
                    document.getElementById('step-pendente').style.display = 'block';
                } else if(d.status === 'aprovado') {
                    document.getElementById('nomePacienteLogado').innerText = d.paciente.nome;
                    document.getElementById('pacienteIdLogado').value = d.paciente.id;
                    document.getElementById('step-agendamento').style.display = 'block';
                }
            });
        }

        function solicitarCadastro() {
            const dados = { cpf: pacienteCpfAtual, nome: document.getElementById('cadNome').value, sus: document.getElementById('cadSus').value, telefone: document.getElementById('cadTel').value };
            if(!dados.nome || !dados.sus || !dados.telefone) { alert("Preencha todos os campos!"); return; }
            
            fetch('api.php?action=solicitar_cadastro', {
                method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(dados)
            }).then(r=>r.json()).then(d => {
                if(d.sucesso) {
                    document.getElementById('step-cadastro').style.display = 'none';
                    document.getElementById('step-pendente').style.display = 'block';
                } else {
                    alert(d.msg);
                }
            });
        }

        // ESCONDE HORÁRIOS JÁ OCUPADOS
        function buscarHorarios() {
            const medico_id = document.getElementById('agenMedico').value;
            const data = document.getElementById('agenData').value;
            const selectHora = document.getElementById('agenHora');
            
            selectHora.innerHTML = '<option value="">Carregando...</option>';
            
            if(!medico_id || !data) { selectHora.innerHTML = '<option value="">Selecione data e médico</option>'; return; }
            
            fetch('api.php?action=horarios_disponiveis', {
                method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({medico_id: medico_id, data: data})
            }).then(r=>r.json()).then(horarios => {
                selectHora.innerHTML = '<option value="">Selecione o horário...</option>';
                if(horarios.length === 0) {
                    selectHora.innerHTML = '<option value="">❌ Agenda Esgotada para este dia</option>';
                } else {
                    horarios.forEach(h => { selectHora.innerHTML += `<option value="${h}">${h}</option>`; });
                }
            });
        }

        function agendarConsulta() {
            const dados = { paciente_id: document.getElementById('pacienteIdLogado').value, medico_id: document.getElementById('agenMedico').value, data: document.getElementById('agenData').value, hora: document.getElementById('agenHora').value };
            if(!dados.medico_id || !dados.data || !dados.hora) { alert("Preencha médico, data e hora da consulta!"); return; }
            
            fetch('api.php?action=agendar', {
                method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(dados)
            }).then(r=>r.json()).then(d => {
                if(d.sucesso) {
                    alert("✅ Sua consulta foi agendada/solicitada com sucesso! Acompanhe o contato da unidade.");
                    voltarInicio();
                } else {
                    alert(d.msg); // Se alguém pegou o horário no mesmo segundo que ele
                    buscarHorarios(); 
                }
            });
        }

        function voltarInicio() {
            document.getElementById('step-cadastro').style.display = 'none';
            document.getElementById('step-agendamento').style.display = 'none';
            document.getElementById('step-pendente').style.display = 'none';
            document.getElementById('step-identificacao').style.display = 'block';
            document.getElementById('cpfBusca').value = '';
        }
    </script>
</body>
</html>
