-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 06/05/2026 às 02:07
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `rede_municipal`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `consultas`
--

CREATE TABLE `consultas` (
  `id` int(11) NOT NULL,
  `paciente_id` int(11) DEFAULT NULL,
  `medico_id` int(11) DEFAULT NULL,
  `unidade_id` int(11) DEFAULT NULL,
  `data_agendamento` date DEFAULT NULL,
  `hora` varchar(10) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `consultas`
--

INSERT INTO `consultas` (`id`, `paciente_id`, `medico_id`, `unidade_id`, `data_agendamento`, `hora`, `status`) VALUES
(1, 2, 3, 2, '2026-04-15', '07:00', 'Aguardando Confirmação'),
(2, 4, 3, 2, '2026-04-15', '10:00', 'Solicitado'),
(3, 5, 3, 2, '2026-04-15', '10:00', 'Aguardando'),
(4, 2, 3, 2, '2026-04-15', '10:00', 'Aguardando'),
(5, 2, 3, 2, '2026-04-15', '10:00', 'Aguardando'),
(6, 2, 7, 1, '2026-04-15', '08:30', 'Aguardando');

-- --------------------------------------------------------

--
-- Estrutura para tabela `encaminhamentos`
--

CREATE TABLE `encaminhamentos` (
  `id` int(11) NOT NULL,
  `paciente_id` int(11) DEFAULT NULL,
  `ubs_id` int(11) DEFAULT NULL,
  `especialidade_destino` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pendente',
  `data_solicitacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `medicos`
--

CREATE TABLE `medicos` (
  `id` int(11) NOT NULL,
  `unidade_id` int(11) DEFAULT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `crm` varchar(50) DEFAULT NULL,
  `especialidade` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `medicos`
--

INSERT INTO `medicos` (`id`, `unidade_id`, `nome`, `crm`, `especialidade`) VALUES
(1, 1, 'Dra. Claudia Regina', 'CRM/SP 3456', 'Cardiologia'),
(2, 1, 'Dr. Renato Bastos', 'CRM/SP 2345', 'Ortopedia'),
(3, 2, 'Dr. Carlos Silva', 'CRM/SP 1111', 'Clínico Geral'),
(4, 11, 'Dra. Mariana Costa', 'CRM/SP 2222', 'Clínico Geral'),
(5, 12, 'Dr. Paulo Mendes', 'CRM/SP 8888', 'Pediatria'),
(6, 1, 'Rafael Revelli', '242424', 'Ginecologista'),
(7, 1, 'João Paulo Ferraz', '33333333', 'Uorologista');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pacientes`
--

CREATE TABLE `pacientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `cpf` varchar(20) DEFAULT NULL,
  `sus` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `prontuario` longtext DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Aprovado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pacientes`
--

INSERT INTO `pacientes` (`id`, `nome`, `cpf`, `sus`, `telefone`, `prontuario`, `status`) VALUES
(1, 'João Paulo Feraz', '2424424242424', 'xxxxxxx', 'xxxxxxxxxxxxxxxxxx', NULL, 'Aprovado'),
(2, 'Alex barbosa dias', '32852901889', 'xxxxxxxxxxxxxxxxxxx', '19974225801', NULL, 'Aprovado'),
(3, 'Paulo Henrique', '4444444444444444', '444444444444444', '', NULL, 'Aprovado'),
(4, 'Alex barbosa dias', '3333333333333333', '33333333333333333333', '19974225801', NULL, 'Aprovado'),
(5, 'Zé da Manga', '1111111111111111', '11111111111111111111', '111111111111', NULL, 'Aprovado'),
(20, 'Shirley Aparecida', '07769925894', '333333333', '19974225801', NULL, 'Pendente');

-- --------------------------------------------------------

--
-- Estrutura para tabela `unidades`
--

CREATE TABLE `unidades` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) DEFAULT NULL,
  `tipo` enum('CENTRAL','UBS') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `unidades`
--

INSERT INTO `unidades` (`id`, `nome`, `tipo`) VALUES
(1, 'CENTRAL DE ESPECIALIDADES MÉDICAS - CEM', 'CENTRAL'),
(2, 'USF Iracema M. Amélia Perondi – CSII', 'UBS'),
(3, 'USF Adalberto Luis Pirondi', 'UBS'),
(4, 'USF Augusto Pirondi', 'UBS'),
(5, 'USF João Malaman', 'UBS'),
(6, 'USF Elza Falco Paschoanelli', 'UBS'),
(7, 'USF Arlindo de Vicente', 'UBS'),
(8, 'USF Antonio Gallo', 'UBS'),
(9, 'USF Darcy Ripa', 'UBS'),
(10, 'USF Valdir Alvares Menendes', 'UBS'),
(11, 'UBS Umberto Ribaldo', 'UBS'),
(12, 'Unidade da Criança', 'UBS');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `unidade_id` int(11) DEFAULT NULL,
  `login` varchar(50) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `unidade_id`, `login`, `senha`) VALUES
(1, 1, 'cem', '123'),
(2, 2, 'usf_iracema', '123'),
(3, 3, 'usf_adalberto', '123'),
(4, 4, 'usf_augusto', '123'),
(5, 5, 'usf_joao', '123'),
(6, 6, 'usf_elza', '123'),
(7, 7, 'usf_arlindo', '123'),
(8, 8, 'usf_antonio', '123'),
(9, 9, 'usf_darcy', '123'),
(10, 10, 'usf_valdir', '123'),
(11, 11, 'ubs_umberto', '123'),
(12, 12, 'crianca', '123');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `consultas`
--
ALTER TABLE `consultas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `paciente_id` (`paciente_id`),
  ADD KEY `medico_id` (`medico_id`);

--
-- Índices de tabela `encaminhamentos`
--
ALTER TABLE `encaminhamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `paciente_id` (`paciente_id`),
  ADD KEY `ubs_id` (`ubs_id`);

--
-- Índices de tabela `medicos`
--
ALTER TABLE `medicos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unidade_id` (`unidade_id`);

--
-- Índices de tabela `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD UNIQUE KEY `sus` (`sus`);

--
-- Índices de tabela `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `unidade_id` (`unidade_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `consultas`
--
ALTER TABLE `consultas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `encaminhamentos`
--
ALTER TABLE `encaminhamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `medicos`
--
ALTER TABLE `medicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `consultas`
--
ALTER TABLE `consultas`
  ADD CONSTRAINT `consultas_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`),
  ADD CONSTRAINT `consultas_ibfk_2` FOREIGN KEY (`medico_id`) REFERENCES `medicos` (`id`);

--
-- Restrições para tabelas `encaminhamentos`
--
ALTER TABLE `encaminhamentos`
  ADD CONSTRAINT `encaminhamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`),
  ADD CONSTRAINT `encaminhamentos_ibfk_2` FOREIGN KEY (`ubs_id`) REFERENCES `unidades` (`id`);

--
-- Restrições para tabelas `medicos`
--
ALTER TABLE `medicos`
  ADD CONSTRAINT `medicos_ibfk_1` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`);

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
