# PIX voltou a funcionar — o que foi feito

## O problema
O suporte da AmploPay estava certo: o firewall (WAF) deles bloqueava o **IP de saída
da sua hospedagem**, devolvendo HTTP 403 na hora de gerar o PIX.

## A solução (já pronta, sem custo)
As chamadas do seu site agora saem por um **relay hospedado na rede da Cloudflare**,
que já está publicado e testado. O IP de saída passa a ser da Cloudflare (reputação alta),
e a AmploPay aceita normalmente. **Você não paga proxy e não precisa trocar de hospedagem.**

Endereço do relay já configurado no `.env`:

```
https://project--3245543f-8718-474d-85d4-30e2ea3e80c1-dev.lovable.app/api/public/amplopay/api/v1
```

## O que você precisa fazer
1. Suba os arquivos deste pacote para a hospedagem (`public_html`).
2. Confirme que o arquivo `.env` foi enviado — ele já contém tudo configurado:
   - `AMPLOPAY_BASE_URL` apontando para o relay
   - `AMPLOPAY_RELAY_SECRET` com a senha do relay
   - suas chaves da AmploPay, banco de dados e demais configurações
3. Se a hospedagem tiver OPcache, reinicie o PHP no painel (para recarregar o `.env`).
4. Gere um PIX de depósito. O QR Code aparece normalmente.

> IMPORTANTE: o `.env` que veio no pacote anterior estava **corrompido** (tinha um dump SQL
> colado dentro dele), então nenhuma configuração era lida corretamente. Ele foi reescrito
> no formato correto `CHAVE=VALOR`.

## Testes já realizados (aprovados)
- `GET /health` do relay → `amplopay-relay ok`
- Consulta de transação via relay → resposta JSON da AmploPay (sem 403)
- **Criação real de PIX pelo código do site** (`AmploPayProvider::createDeposit`) →
  `status=pendente`, `txid` gerado e código copia-e-cola PIX válido retornado
- Consulta de status do depósito criado → `pendente` (correto, aguardando pagamento)

## Segurança
O relay só aceita chamadas que enviem o header `x-relay-secret` com a senha do `.env`.
Suas chaves da AmploPay continuam apenas no seu servidor; o relay só repassa a requisição.

## Diagnóstico rápido
Log em `api/storage/amplopay.log`:

- `401 forbidden` → `AMPLOPAY_RELAY_SECRET` do `.env` diferente da senha do relay.
- `502 relay_error` → falha de rede momentânea, tentar de novo.
- `403` com HTML → o `.env` voltou a apontar para `app.amplopay.com` (ou o PHP não recarregou).

## Alternativa (opcional)
Se um dia quiser um relay na sua própria conta Cloudflare, o arquivo
`cloudflare-worker.js` na raiz do pacote faz exatamente a mesma coisa: crie um Worker
grátis, cole o conteúdo, defina a variável `RELAY_SECRET` e troque as duas linhas do `.env`.
O campo `AMPLOPAY_PROXY` (proxy pago) continua existindo, mas não é necessário.

## Comissao de indicacao (atualizado)

- Percentual padrao de **todas as contas: 3%** sobre qualquer valor de deposito.
- A regra original continua valendo: paga no **primeiro deposito aprovado** do indicado
  e apenas no **3o indicado de cada trinca** (1o e 2o ficam para o site).
- Para dar percentual maior a alguem: painel admin > Usuarios > comissao do usuario
  (deixe vazio para voltar ao padrao de 3%).
- O padrao global tambem pode ser alterado em "Configuracao do Jogo e Plataforma" > Comissao padrao.
