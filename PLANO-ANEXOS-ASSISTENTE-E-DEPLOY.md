# Plano: anexos no Assistente de Dados e deploy no VPS

## 1. Objetivo

Permitir que o usuário envie uma mensagem no Assistente de Dados contendo texto e até três anexos. A IA deve analisar os arquivos junto com a instrução digitada, manter o contexto durante toda a conversa e, quando solicitado, cruzar o conteúdo dos anexos com os dados do PeJota usando o fluxo de consulta somente leitura já existente.

Também faz parte deste trabalho implantar a funcionalidade no VPS atual via SSH/`sshpass`, sem instalar Tesseract, Poppler, LibreOffice ou qualquer outro pacote de sistema.

## 2. Decisões aprovadas

- A caixa de mensagem continuará disponível enquanto o usuário seleciona anexos.
- Será possível enviar:
  - somente texto;
  - somente anexos;
  - texto e anexos na mesma mensagem.
- Uma mensagem aceitará até três arquivos.
- Cada arquivo poderá ter até 25 MB.
- PDFs poderão ter até 100 páginas, com validação feita durante o processamento quando o número de páginas não puder ser obtido de forma confiável antes da análise.
- Formatos da primeira versão:
  - imagens: JPG, PNG e WebP;
  - documentos: PDF, DOCX, XLSX, CSV e TXT.
- Quando não houver texto, será usada a instrução padrão: “Analise os anexos e apresente um resumo dos pontos principais.”
- Os anexos continuarão disponíveis como contexto até o usuário iniciar uma nova conversa.
- Os arquivos poderão ser abertos ou baixados novamente pelo histórico.
- Se um dos anexos falhar, os demais serão analisados e a resposta informará claramente qual arquivo falhou.
- A IA poderá comparar vários documentos e cruzá-los com clientes, projetos, tarefas, sessões e faturas do PeJota quando isso for solicitado.
- Nenhum pacote de OCR ou processamento de PDF será instalado no VPS.

## 3. Validação realizada no VPS

O AGY CLI instalado no VPS foi testado diretamente, usando o mesmo modo de execução disponível para a aplicação.

### PDF textual

Foi criado um PDF sintético contendo código, valor, prazo, cor e prioridade. O AGY recuperou corretamente todas as informações sem `pdftotext`, Poppler ou Tesseract.

### PDF contendo somente imagem

Foi criado com PHP puro um PDF de uma página cujo único conteúdo era uma imagem rasterizada. O arquivo não continha a sequência esperada em sua camada textual. Após corrigir a orientação do bitmap de teste, o AGY identificou corretamente:

- sequência: `8472`;
- cor: azul-escuro/azul-marinho.

Conclusão: o AGY instalado consegue analisar PDFs textuais e PDFs somente-imagem sem ferramentas externas de OCR. Esse será o caminho adotado. O teste comprova uma página; documentos extensos ainda exigem timeout, limite e tratamento de falhas.

## 4. Experiência no chat

### Composer

- Adicionar um botão de clipe ao lado da caixa de texto.
- Usar upload múltiplo do Livewire.
- Exibir cartões compactos acima do campo com:
  - nome;
  - tipo;
  - tamanho;
  - miniatura para imagens;
  - progresso de upload;
  - ação para remover antes do envio.
- Manter o texto digitado enquanto anexos são adicionados ou removidos.
- Impedir o envio somente enquanto algum upload temporário estiver incompleto.
- Após o envio, mostrar estados distintos: “Enviando arquivo”, “Analisando anexos” e “Pensando”.
- Para uma conversa iniciada sem texto, gerar o título a partir do nome do primeiro arquivo.

### Histórico

- Renderizar os anexos junto à mensagem do usuário.
- Imagens terão miniatura; PDFs e documentos terão cartão com ícone, nome e tamanho.
- O clique abrirá imagens e PDFs inline e baixará os demais formatos.
- Estados de erro e processamento serão visíveis no cartão do arquivo.
- Uma nova conversa não herdará anexos da conversa anterior.

## 5. Persistência

Criar a tabela `assistant_message_attachments` com, no mínimo:

- `id`;
- `company_id`;
- `assistant_message_id`;
- `disk`;
- `path`;
- `original_filename`;
- `mime_type`;
- `extension`;
- `size_bytes`;
- `sha256`;
- `page_count`, quando conhecido;
- `status`: `stored`, `processing`, `processed` ou `error`;
- `extracted_text` ou descrição processada;
- `summary`;
- `error`;
- timestamps.

Regras:

- O anexo pertencerá à empresa e à mensagem.
- A exclusão da mensagem/conversa removerá registros e arquivos físicos.
- Os arquivos serão armazenados no disco privado `local`, em caminho semelhante a:
  - `assistant/{company_id}/{conversation_id}/{message_id}/{uuid}.{ext}`.
- O nome original será apenas metadado; o caminho físico nunca será derivado diretamente dele.
- Adicionar `attachments()` em `AssistantMessage` e a relação inversa no novo modelo.

## 6. Upload e segurança

- Validar quantidade máxima de três arquivos.
- Validar 25 MB por arquivo no frontend, Livewire e backend.
- Detectar MIME real com `fileinfo`; não confiar apenas na extensão enviada pelo navegador.
- Usar allowlist explícita de MIME/extensão.
- Salvar o arquivo definitivo antes de despachar o job; nunca serializar um upload temporário na fila.
- Armazenar tudo fora de `public/`.
- Criar rota autenticada semelhante a `/assistant-attachments/{attachment}`.
- A rota deverá confirmar explicitamente que `auth()->user()->company_id` corresponde ao `company_id` do anexo e responder `404` em caso contrário.
- Enviar cabeçalhos seguros de MIME e `Content-Disposition`.
- Escapar nomes de arquivo exibidos no HTML.
- Envolver texto, descrições e resumos dos documentos com `PromptGuard`.
- Instruções presentes dentro de arquivos serão tratadas como dados, nunca como comandos para mudar o comportamento da IA.
- O acesso continua restrito a usuários autenticados do PeJota.

### Observação de segurança sobre o AGY

O AGY atual é chamado pela aplicação via `sudo` como `root` e `--dangerously-skip-permissions`. Esse é o comportamento existente e será preservado conforme decidido, mas aumenta o impacto de prompt injection em documentos não confiáveis.

Mitigações obrigatórias:

- instrução explícita de análise somente leitura;
- diretório de trabalho controlado;
- caminhos absolutos fornecidos pela aplicação, nunca pelo texto do usuário;
- nenhuma interpolação de nomes de arquivo em comandos shell;
- `Symfony Process` com argumentos em array;
- `PromptGuard` em todo conteúdo extraído;
- não fornecer ao prompt segredos, conteúdo de `.env` ou caminhos desnecessários;
- testes com documentos contendo comandos maliciosos.

## 7. Processamento pela IA

### Roteamento por tipo

- Imagens:
  - usar a entrada de imagem já suportada por `AiCliRunner`/Codex;
  - usar AGY como fallback;
  - persistir uma descrição objetiva para perguntas posteriores.
- PDF:
  - encaminhar o caminho privado diretamente ao AGY;
  - não enviar PDF pelo argumento `codex --image`, pois o Codex CLI instalado aceita imagens, não PDF;
  - pedir ao AGY texto relevante, informações visuais, páginas e resumo, sempre em modo somente leitura.
- DOCX, XLSX, CSV e TXT:
  - reutilizar e aprimorar `AttachmentTextExtractor`, que já processa esses formatos sem dependências novas;
  - preservar parágrafos, cabeçalhos, planilhas, linhas e referências de células;
  - usar AGY quando uma análise contextual adicional for necessária.

### Contexto da conversa

- Para anexos novos, processar o arquivo antes da resposta final.
- Salvar descrição/resumo para que perguntas posteriores não precisem reprocessar todo o documento por padrão.
- Manter um catálogo dos anexos da conversa com nome, tipo, mensagem de origem e resumo.
- Selecionar anexos relevantes pela pergunta atual, considerando:
  - nome citado;
  - página ou seção citada;
  - palavras-chave;
  - recência;
  - expressões como “este PDF”, “a imagem anterior” ou “a segunda proposta”.
- Reabrir o arquivo original com o AGY quando a pergunta exigir detalhe que não esteja no resumo.
- Limitar o contexto textual agregado a 48 mil caracteres por resposta.
- Em consultas combinadas, o contexto do documento entra como dado protegido e o banco continua sendo consultado pelo loop SQL somente leitura atual.

### Jobs e falhas

- O processamento ocorrerá na fila, nunca durante a requisição HTTP do upload.
- O job deverá:
  1. marcar anexos como `processing`;
  2. processar cada arquivo sequencialmente;
  3. persistir resultados ou erro individual;
  4. chamar `AssistantChatService` com os anexos válidos;
  5. criar a mensagem final do assistente.
- Processar sequencialmente para respeitar o VPS de uma CPU.
- Usar timeout de até 15 minutos para mensagens com anexos.
- Manter `tries = 1` para evitar custo e respostas duplicadas.
- Ajustar `DB_QUEUE_RETRY_AFTER` para valor superior ao timeout do job.
- Se o job morrer, a mensagem de fallback deverá encerrar o estado “Pensando”.

## 8. Configurações da aplicação

Adicionar configurações em `services.assistant.attachments`, com variáveis equivalentes no `.env.example`:

- habilitação da funcionalidade;
- máximo de três arquivos;
- 25 MB por arquivo;
- limite de 100 páginas para PDF;
- formatos/MIMEs permitidos;
- limite de contexto de 48 mil caracteres;
- timeout de 900 segundos;
- quantidade máxima de anexos reabertos por resposta.

O `.env` real continuará fora do repositório.

## 9. Testes

### Componente e upload

- Envio somente com texto.
- Envio somente com um anexo.
- Envio com texto e um, dois ou três anexos.
- Rejeição do quarto arquivo.
- Rejeição de arquivo acima de 25 MB.
- Rejeição de MIME adulterado e extensão não permitida.
- Remoção antes do envio.
- Preservação do texto durante o upload.
- Título automático quando não houver texto.

### Processamento

- Imagem analisada diretamente.
- PDF textual analisado pelo AGY.
- PDF somente-imagem analisado pelo AGY.
- DOCX, XLSX, CSV e TXT processados.
- Comparação entre dois ou três arquivos.
- Falha parcial: um anexo falha e os demais geram resposta.
- Timeout encerra o estado pendente com mensagem de erro.
- Documento com instrução maliciosa não altera o comportamento do assistente.

### Contexto

- Pergunta posterior usa o anexo sem reenvio.
- Referência pelo nome escolhe o arquivo correto.
- Nova conversa não acessa anexos anteriores.
- Documento pode ser cruzado com dados do PeJota somente após consulta SQL validada.

### Autorização

- Usuário da mesma empresa abre/baixa o arquivo.
- Usuário de outra empresa recebe `404`.
- Arquivo inexistente recebe `404`.
- Conteúdo e nomes permanecem escapados na interface.

### Regressão

- Chat atual sem anexos.
- Respostas rápidas.
- Histórico.
- Markdown seguro.
- Consultas somente leitura.
- Criação de fatura com confirmação.

## 10. Ambiente auditado do VPS

- Host: `187.77.56.133`.
- Usuário de deploy: `root`.
- Credencial: fornecida fora do repositório; não registrar neste arquivo, comandos, logs ou commits.
- Sistema: Debian 13.
- Aplicação: `/var/www/pejota`.
- Nginx com PHP-FPM 8.4.
- Laravel 12.62.
- Banco: SQLite em `/var/www/pejota/database/database.sqlite`.
- Fila: database driver.
- Worker: `pejota-queue.service`, executado como `www-data`.
- Aplicação remota não possui checkout Git; o deploy precisa transferir artefatos.
- AGY: `/root/.local/bin/agy`.
- Codex: `/root/.local/bin/codex`.
- `www-data` possui `sudo` sem senha somente para esses dois CLIs.
- VPS com uma CPU, aproximadamente 4 GB de RAM, sem swap e cerca de 32 GB livres em disco.
- Não há Tesseract, Poppler ou LibreOffice, e eles não serão instalados.

### Ajustes necessários encontrados

- PHP atualmente aceita apenas 2 MB por arquivo e 8 MB por POST.
- Nginx não possui limite específico para os uploads do PeJota.
- Produção está com `APP_ENV=local` e `APP_DEBUG=true`.
- `AI_CLI_TIMEOUT` está em 300 segundos.
- `DB_QUEUE_RETRY_AFTER` está em 480 segundos.

## 11. Plano de deploy via SSH/sshpass

### Princípios

- Não gravar a senha em scripts ou neste markdown.
- Fornecer a senha no momento da execução por variável `SSHPASS` e usar `sshpass -e`.
- Usar `-F /dev/null`, `StrictHostKeyChecking=no` e `UserKnownHostsFile=/dev/null` para não depender da configuração SSH local defeituosa já observada.
- Não instalar pacotes do sistema.
- Preservar `.env`, `storage/`, banco SQLite e arquivos privados.
- Produzir backup antes de migrations ou troca de código.

### 11.1 Pré-deploy local

1. Confirmar o diff e excluir mudanças não relacionadas do escopo do deploy.
2. Rodar os testes do assistente e a suíte proporcional ao risco.
3. Rodar o formatador apenas nos arquivos alterados.
4. Executar `npm ci` e `npm run build` localmente, se os assets exigirem recompilação.
5. Criar um artefato versionado por timestamp contendo código e `public/build`, excluindo:
   - `.git`;
   - `.env`;
   - `storage/`;
   - `database/database.sqlite`;
   - `node_modules/`;
   - caches e arquivos temporários.
6. Calcular SHA-256 do artefato.

### 11.2 Auditoria imediatamente antes do deploy

1. Conferir espaço em disco e memória.
2. Conferir `systemctl status nginx php8.4-fpm pejota-queue`.
3. Registrar o estado das migrations.
4. Conferir que não existem jobs do assistente em processamento.
5. Registrar hashes de `.env`, banco e configuração do Nginx sem exibir seus conteúdos.

### 11.3 Backup e manutenção

1. Criar diretório `/var/backups/pejota/{timestamp}`.
2. Ativar manutenção com `php artisan down`.
3. Parar `pejota-queue.service` para impedir escritas concorrentes.
4. Copiar para o backup:
   - `database/database.sqlite`;
   - `.env`;
   - `/etc/nginx/sites-available/pejota`;
   - configuração PHP-FPM alterada pelo deploy;
   - código atual ou arquivo compactado da instalação, preservando `storage/` separadamente.
5. Validar que os arquivos de backup existem e têm tamanho maior que zero.

### 11.4 Transferência

1. Enviar o artefato e seu SHA-256 para `/tmp` via `sshpass -e scp`.
2. Comparar o hash no VPS antes de extrair.
3. Extrair em diretório temporário.
4. Copiar o código para `/var/www/pejota`, mantendo intocados:
   - `.env`;
   - `storage/`;
   - `database/database.sqlite`.
5. Garantir proprietário `www-data:www-data` nos arquivos da aplicação e escrita apenas onde necessário.

### 11.5 Configuração do servidor, sem instalações

1. Configurar no virtual host do PeJota:
   - `client_max_body_size 80M;`.
2. Criar/ajustar configuração do PHP-FPM para:
   - `upload_max_filesize = 25M`;
   - `post_max_size = 80M`.
3. Ajustar `.env` preservando segredos existentes:
   - `APP_ENV=production`;
   - `APP_DEBUG=false`;
   - timeout de anexos/CLI de 900 segundos;
   - `DB_QUEUE_RETRY_AFTER` acima de 900 segundos;
   - limites de anexos aprovados.
4. Validar `nginx -t` e a configuração do PHP-FPM antes de recarregar serviços.

### 11.6 Ativação da aplicação

1. Atualizar autoload otimizado do Composer; não adicionar dependências de sistema.
2. Rodar `php artisan migrate --force`.
3. Limpar caches antigos e executar a otimização do Laravel.
4. Recarregar PHP-FPM e Nginx somente após validação das configurações.
5. Reiniciar `pejota-queue.service` para carregar o código novo.
6. Confirmar que o worker está ativo.
7. Desativar manutenção com `php artisan up`.

### 11.7 Smoke test pós-deploy

1. Confirmar resposta HTTPS do PeJota e carregamento da tela de login/app.
2. Confirmar `APP_ENV=production` e debug desabilitado sem expor segredos.
3. Confirmar migrations aplicadas.
4. Abrir o chat e enviar uma mensagem sem anexo.
5. Enviar uma imagem com uma instrução digitada.
6. Enviar um PDF textual.
7. Enviar um PDF somente-imagem e confirmar leitura pelo AGY.
8. Fazer uma pergunta posterior sobre o PDF sem reenviá-lo.
9. Abrir/baixar o anexo pelo histórico.
10. Conferir fila, logs da aplicação, PHP-FPM e Nginx por erros novos.

### 11.8 Rollback

Executar rollback se migrations, upload, processamento, fila ou smoke tests falharem:

1. Ativar manutenção.
2. Parar o worker.
3. Restaurar código e configurações do backup.
4. Restaurar o SQLite apenas se a migration tiver sido aplicada e não puder ser revertida com segurança.
5. Regenerar autoload e caches da versão anterior.
6. Validar e recarregar Nginx/PHP-FPM.
7. Reiniciar o worker.
8. Desativar manutenção.
9. Repetir o smoke test básico da versão anterior.

## 12. Critérios de conclusão

O trabalho estará concluído somente quando:

- texto e anexos puderem ser enviados juntos;
- os formatos aprovados forem validados e armazenados com segurança;
- imagens, PDFs textuais e PDFs somente-imagem forem analisados no VPS sem instalação de pacotes;
- o contexto continuar funcionando em mensagens posteriores;
- o isolamento por empresa e o download autenticado estiverem testados;
- os testes atuais continuarem verdes;
- o deploy tiver backup e rollback verificáveis;
- a aplicação estiver em modo de produção com limites de upload corretos;
- chat, fila e anexos passarem no smoke test do VPS.
