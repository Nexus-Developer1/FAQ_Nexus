# Knowledgebase Nexus — Nexus Solutions

Aplicação web interna onde os técnicos consultam procedimentos de reparação e os
responsáveis os inserem e editam numa área de administração.

- **Consulta** (`/`): pesquisa por texto, filtro por categoria, regras de segurança
  no topo, impressão em A4 (um procedimento por página). **Requer sessão iniciada** —
  toda a aplicação é de acesso interno.
- **Administração** (`/admin`): três perfis:
  - **Editor** — é quem, na **área técnica** ou na **produção**, carrega problemas e
    soluções: cria e edita procedimentos.
  - **Administrador** — tudo isso mais: gerir contas, categorias, regras de
    segurança e apagar definitivamente.
  - **Leitor** — só consulta e imprime. Não altera nada e não tem acesso à área
    de administração. É o perfil para quem apenas precisa de seguir procedimentos
    na bancada.
  Cada conta tem uma área (Área técnica / Produção); quem criou ou alterou cada
  procedimento fica registado com nome e área (ex.: "Rita Silva (Produção)").

**Separação por área:** cada procedimento pertence à *Área técnica* ou à
*Produção*, e cada pessoa só vê os da sua área — os administradores vêem todas.
O conteúdo carregado inicialmente é todo da Área técnica. As *regras de
segurança* são as mesmas para toda a gente.

**Como se criam as contas:** o administrador (`suporte@nxs.pt`) vai a
Administração → Utilizadores → Nova conta e indica nome, email, área e perfil.
A pessoa recebe um **email com um link para definir a sua palavra-passe** (válido
3 dias). Ninguém — nem o administrador — escolhe a palavra-passe de outra pessoa.
Se o link expirar, a pessoa usa "Esqueci-me da palavra-passe" na página de entrada,
ou o administrador carrega em "Enviar convite" na lista de utilizadores.

Os emails saem pela conta **Suporte@nxs.pt** através do Microsoft Graph, com as
mesmas credenciais do Entra ID que a Nexus Ops já usa (variáveis `MS_GRAPH_*` no
`.env` do servidor).

Tecnologia: **PHP 8.3 + Laravel 13 + PostgreSQL**. Sem Node, sem compilação, sem
serviços externos. As páginas são geradas no servidor; há uma folha de estilos
(`public/css/app.css`) e um ficheiro JavaScript (`public/js/app.js`), ambos simples.

---

## 0. Faz parte de uma suite

A entrada é feita no **Nexus Portal** (`/portal`), que trata do login (com
verificação em duas etapas) e decide quem pode abrir cada aplicação. Esta
aplicação já não tem login próprio.

As pessoas vêm da tabela `utilizadores`, partilhada com a Nexus Infra. O que
cada uma **faz aqui dentro** — administrador, editor ou leitor, e a área —
continua a ser decidido aqui, em Administração → Perfis.

Para a sessão ser partilhada, quatro definições do `.env` têm de coincidir com
as das outras aplicações: `APP_KEY`, `SESSION_COOKIE`, `REDIS_PREFIX` e o
espaço de sessão (`SESSION_STORE=sessao`, ver `config/cache.php`). O
`SESSION_SERIALIZATION=php` existe porque as outras correm numa versão do
Laravel anterior ao formato JSON.

## 1. Como está organizada a aplicação

| Onde | O que é |
|---|---|
| `app/Models/` | Os dados: `Procedure` (procedimento), `ProcedureStep` (passo), `Category`, `SafetyRule`, `User` |
| `app/Http/Controllers/` | O que acontece em cada página (consulta, login, administração) |
| `app/Http/Requests/ProcedureRequest.php` | Regras de validação do formulário de procedimento |
| `app/Console/Commands/` | Comandos de terminal: criar admin, alterar palavra-passe |
| `database/migrations/` | Estrutura das tabelas |
| `resources/views/` | As páginas (HTML) |
| `lang/pt_PT/` | Mensagens em português de Portugal |
| `public/css`, `public/js` | Aspecto visual e comportamento no browser |
| `deploy/` | Scripts de instalação, configuração do Nginx e cópias de segurança |
| `tests/Feature/AplicacaoTest.php` | Testes automáticos (20 cenários) |

Cada procedimento tem: referência automática (`PROC-01`, `PROC-02`… nunca reutilizada),
título, **problema / sintomas**, categoria, área (técnica ou produção), passos
ordenados (a solução), "o que registar no ticket", "quando escalar", datas de
criação e de última alteração, e quem alterou.

Segurança: palavras-passe com *hash* (bcrypt), sessão expira após **8 horas** sem
uso (`SESSION_LIFETIME` no `.env`, em minutos), máximo de 5 tentativas de login por
minuto, protecção CSRF em todos os formulários, cookies só por HTTPS em produção.

---

## 2. Experimentar no seu PC (Windows) antes de instalar no servidor

Precisa de **PHP 8.3** (já tem), **Composer** (vem incluído na pasta como
`composer.phar`) e um **PostgreSQL** (pode ser em Docker).

**Neste PC já está tudo preparado**: as dependências estão instaladas, o PostgreSQL
de testes (`faq-pg`) está criado em Docker e a conta de administrador local é
`admin@nxs.pt` / `palavrapasse123` (só para testes; no servidor cria outra).
Para arrancar basta `docker start faq-pg` e depois `php artisan serve`.

Num PC novo, os passos completos são:

```powershell
# 1) Dentro da pasta da aplicação
php composer.phar install

# 2) PostgreSQL de testes em Docker (uma vez)
docker run -d --name faq-pg -e POSTGRES_USER=faq -e POSTGRES_PASSWORD=faq -e POSTGRES_DB=faq -p 5433:5432 postgres:16-alpine

# 3) Configuração local (o .env já existe e aponta para este PostgreSQL na porta 5433)
php artisan migrate

# 4) Conta de administrador
php artisan app:criar-admin

# 5) Arrancar
php artisan serve
```

Abra <http://localhost:8000>. Para parar: `Ctrl+C`. Para arrancar de novo noutro dia:
`docker start faq-pg` e `php artisan serve`.

Testes automáticos: `php artisan test`.

---

## 3a. Onde está instalada (servidor da Nexus, 192.168.1.69)

A aplicação está instalada no servidor interno `linuxdev` (192.168.1.69), lado a
lado com a Nexus Ops, servida pelo **Apache** já existente e com o **mesmo
certificado HTTPS** (Let's Encrypt de `infra.nexus-solutions.pt`):

- **Endereço:** `https://infra.nexus-solutions.pt:9443/knowledgebase-nexus`
- **Administração:** `https://infra.nexus-solutions.pt:9443/knowledgebase-nexus/admin`
- O endereço antigo (`/procedimentos`) redirecciona para o novo, preservando o
  caminho e os parâmetros — favoritos e links de emails antigos continuam a funcionar.
- Pasta no servidor: `/var/www/procedimentos` · Base de dados PostgreSQL: `procedimentos`
- Configuração Apache: `/etc/apache2/conf-available/procedimentos.conf`
  (cópia em [deploy/apache-subpasta.conf](deploy/apache-subpasta.conf)); o ficheiro
  da Nexus Ops não foi alterado.
- A pasta no servidor continua a chamar-se `procedimentos`; só o endereço público
  mudou. Se mudar o endereço, é preciso acertar três sítios: o `Alias` e o
  `RewriteBase` na configuração do Apache, e `APP_URL`/`SESSION_PATH` no `.env`
  (o `SESSION_PATH` tem de coincidir com o endereço, senão a sessão não se mantém).
- Cópias de segurança: todos os dias às 02:30 para `/var/backups/procedimentos/`
  (comando manual: `sudo backup-procedimentos`).
- Neste servidor **não** se usa `php artisan route:cache` (dá erro 405 na página
  inicial quando a aplicação vive numa sub-pasta). `config:cache` e `view:cache` sim.

Porquê uma sub-pasta e não `procedimentos.nexus-solutions.pt`? Porque esse nome
ainda não existe no DNS e eu não tenho acesso ao DNS nem ao router. Quando o
criarem (registo A para o mesmo IP público e NAT para a porta 443), passa-se para
subdomínio próprio: novo `VirtualHost`, `certbot --apache -d procedimentos.nexus-solutions.pt`
e `APP_URL`/`SESSION_PATH` no `.env`.

### Actualizar a aplicação neste servidor
O código chega por **git** (repositório `Nexus-Developer1/FAQ_Nexus`) e a aplicação corre
com o utilizador próprio **`app-faq`** (dono da pasta; o grupo `www-data` só lê). Nunca
se altera código diretamente no servidor: muda-se no PC, faz-se commit e push, e no
servidor só se puxa.
```bash
ssh dev@192.168.1.69
sudo backup-procedimentos
cd /var/www/procedimentos
sudo -u app-faq git pull --ff-only
# só se o composer.lock mudou:
sudo -u app-faq env HOME=/tmp COMPOSER_HOME=/tmp/composer composer install --no-dev --optimize-autoloader --no-interaction
sudo -u app-faq php artisan migrate --force
sudo -u app-faq php artisan config:cache && sudo -u app-faq php artisan view:cache && sudo -u app-faq php artisan route:clear
```
- **Não** usar `php artisan optimize` nem `route:cache` aqui: o `optimize` inclui o
  `route:cache`, e em sub-pasta a página inicial passa a dar **405** («The GET method is
  not supported for route /. Supported methods: HEAD»). Se acontecer: `route:clear`.
- **Não** fazer `chown … www-data`: a pasta é do `app-faq` (o git e as caches dependem disso).

---

## 3b. Instalar no servidor (Ubuntu 22.04 ou 24.04) — instalação nova, de raiz

### Antes de começar, precisa de:
- Um servidor Ubuntu com acesso `sudo` (um VPS de 1 GB de RAM chega perfeitamente).
- Um domínio (ex.: `procedimentos.nexus.pt`) a apontar para o IP do servidor
  (registo DNS do tipo **A**). Sem isto o HTTPS automático não funciona.
- Portas 80 e 443 abertas na firewall.

### Instalação automática (recomendado)

1. Copie a pasta da aplicação para o servidor (por exemplo com WinSCP ou `scp`)
   para `/root/FAQ`. Não precisa de copiar a pasta `vendor`.

2. No servidor, corra:
   ```bash
   sudo bash /root/FAQ/deploy/instalar.sh procedimentos.nexus.pt
   ```
   (substitua pelo vosso domínio). O script instala PHP, PostgreSQL, Nginx e
   Certbot, cria a base de dados com uma palavra-passe aleatória, copia a aplicação
   para `/var/www/procedimentos`, configura o HTTPS e agenda as cópias de segurança
   diárias. Demora 2 a 5 minutos.

3. Crie a conta de administrador (pede nome, email e palavra-passe):
   ```bash
   cd /var/www/procedimentos && sudo -u www-data php artisan app:criar-admin
   ```

4. Abra `https://procedimentos.nexus.pt`. Entre em **Administração**, crie as
   categorias, as regras de segurança e os procedimentos.

O script pode correr-se outra vez sem problemas (não apaga dados nem o `.env`).

### O que o script fez, em linguagem simples
- **Nginx** é o "porteiro": recebe os pedidos da internet e entrega-os ao PHP.
- **PHP-FPM** corre a aplicação.
- **PostgreSQL** guarda os dados (base `procedimentos`, utilizador `procedimentos`).
- **Certbot** obtém um certificado HTTPS gratuito (Let's Encrypt) e renova-o sozinho.
- O ficheiro **`/var/www/procedimentos/.env`** tem a configuração (domínio, palavra-passe
  da base de dados, etc.). Guarde-o bem; não o partilhe.

### Instalação manual (se preferir fazer passo a passo)
Os passos são exactamente os do script `deploy/instalar.sh`, que está comentado.
Resumo: instalar pacotes → criar base e utilizador no PostgreSQL → copiar a
aplicação para `/var/www/procedimentos` → `cp .env.example .env` e preencher →
`composer install --no-dev` → `php artisan key:generate` → `php artisan migrate --force`
→ `php artisan config:cache route:cache view:cache` → copiar `deploy/nginx.conf` para
`/etc/nginx/sites-available/` → `certbot --nginx -d dominio` → `app:criar-admin`.

---

## 4. Cópias de segurança da base de dados

Todos os dados (procedimentos, categorias, regras, conta de administrador) estão
na base PostgreSQL. O script `deploy/backup.sh` exporta-a para um ficheiro
comprimido com a data.

**Já está automatizado** pela instalação: todos os dias às 02:30 é criada uma
cópia em `/var/backups/procedimentos/`, e cópias com mais de 30 dias são apagadas.

Fazer uma cópia à mão (por exemplo antes de uma actualização):
```bash
sudo backup-procedimentos
```
Ver as cópias existentes:
```bash
ls -lh /var/backups/procedimentos/
```

**Muito importante:** o servidor pode avariar ou ser apagado. Copie regularmente
a pasta `/var/backups/procedimentos/` para fora do servidor (para o vosso PC, um
NAS ou uma cloud). Por exemplo, a partir do seu PC com WinSCP, ou com:
```bash
scp -r utilizador@servidor:/var/backups/procedimentos/ D:\Backups\
```

### Restaurar uma cópia
```bash
# 1) Escolher a cópia
ls /var/backups/procedimentos/

# 2) Pôr a aplicação em manutenção (fecha as ligações à base)
cd /var/www/procedimentos && sudo -u www-data php artisan down
sudo systemctl restart php8.3-fpm

# 3) Limpar a base actual e repor a cópia (substitua a data pelo ficheiro certo)
sudo -u postgres psql -c "DROP DATABASE procedimentos;"
sudo -u postgres psql -c "CREATE DATABASE procedimentos OWNER procedimentos ENCODING 'UTF8' TEMPLATE template0;"
gunzip -c /var/backups/procedimentos/procedimentos-2026-08-19_0230.sql.gz | sudo -u postgres psql procedimentos

# 4) Voltar a abrir
sudo -u www-data php artisan up
```
Se mudar de servidor: instale com o `instalar.sh`, depois restaure a cópia com
os comandos acima, e copie também o ficheiro `.env` antigo (tem a chave `APP_KEY`).

---

## 5. Tarefas do dia-a-dia no servidor

| Quero… | Comando (em `/var/www/procedimentos`) |
|---|---|
| Criar contas para técnicos/produção | Administração → **Utilizadores** → Nova conta (a pessoa recebe email para definir a palavra-passe) |
| Alguém não recebeu / o link expirou | Administração → Utilizadores → **Enviar convite** |
| Mudar a palavra-passe de alguém | Não é preciso: a pessoa usa "Esqueci-me da palavra-passe". Em último recurso: `sudo -u www-data php artisan app:alterar-password --email=...` |
| Recuperar acesso de administrador | `sudo -u www-data php artisan app:criar-admin` (com o mesmo email actualiza; com outro cria nova conta de administrador) |
| Ver erros da aplicação | `sudo tail -n 100 storage/logs/laravel.log` |
| Ver se o Nginx/PHP estão a correr | `systemctl status nginx php8.3-fpm postgresql` |
| Pôr em manutenção temporária | `sudo -u www-data php artisan down` / `... artisan up` |

### Actualizar a aplicação para uma nova versão
1. Faça uma cópia de segurança: `sudo backup-procedimentos`
2. Copie a nova versão para `/root/FAQ` (substituindo a antiga).
3. Corra outra vez `sudo bash /root/FAQ/deploy/instalar.sh o-vosso-dominio.pt`.
   Ele actualiza os ficheiros, aplica alterações à base de dados e limpa as caches,
   sem tocar no `.env` nem nos dados.

### Tempo de sessão
No `.env`, `SESSION_LIFETIME=480` são 480 minutos (8 horas) sem actividade.
Depois de alterar o `.env`, correr `sudo -u www-data php artisan config:cache`.

---

## 6. Problemas comuns

- **"A sessão expirou" (erro 419) ao guardar** — a página esteve aberta demasiado
  tempo; volte a entrar e repita. Os dados do formulário perdem-se, por isso em
  textos longos guarde com frequência.
- **Não consigo obter o HTTPS** — confirme que o domínio já aponta para o IP do
  servidor (`ping o-vosso-dominio.pt`) e que as portas 80/443 estão abertas.
  Depois: `sudo certbot --nginx -d o-vosso-dominio.pt`.
- **Erro 500 depois de mexer no `.env`** — correr `sudo -u www-data php artisan config:cache`
  e ver `storage/logs/laravel.log`.
- **Esqueci a palavra-passe do administrador** — `sudo -u www-data php artisan app:alterar-password`.
- **Página em branco / permissões** — `sudo chown -R www-data:www-data /var/www/procedimentos`.

---

## 7. Decisões tomadas (e porquê)

- **Consulta sem login** (como pediu na conversa): os procedimentos ficam visíveis
  a quem tiver o endereço. Se um dia quiser exigir login também na consulta,
  basta mover as rotas de consulta para dentro do grupo `auth` em `routes/web.php`.
- **Contas criadas por um administrador** (sem registo público, sem recuperação
  por email): menos superfície de ataque. A primeira conta é criada por comando
  (`app:criar-admin`); as restantes pela interface, com perfil e área.
- **Sem validação prévia**: o que técnicos e produção inserem publica-se de
  imediato, como diz o pedido original. Se vier a ser preciso um passo de
  aprovação, acrescenta-se um estado "pendente" aos procedimentos.
- **Referências nunca reutilizadas**: há um contador (`tabela counters`) que só
  aumenta; apagar o PROC-07 não faz o próximo ser PROC-07 outra vez — evita
  confusões em papéis já afixados na oficina.
- **Sem histórico de versões** (como escolheu): fica o autor e a data da última
  alteração; as cópias diárias cobrem enganos.
- **Sem compilação de CSS/JS**: ficheiros simples em `public/`, para que qualquer
  ajuste visual seja editar um ficheiro e recarregar.
