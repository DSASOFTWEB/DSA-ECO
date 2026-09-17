Schemas XSD GISS ABRASF 2.04
============================

Pasta padrao lida pelo sistema (NAO precisa copiar para outro lugar):

  storage/SchemasXSDgiss/

Arquivos obrigatorios:
  - tipos-v2_04.xsd
  - enviar-lote-rps-envio-v2_04.xsd
  - consultar-lote-rps-envio-v2_04.xsd
  - cabecalho-v2_04.xsd
  - xmldsig-core-schema20020212.xsd

Config opcional no .env:
  NFSE_GISS_SCHEMAS_PATH=/caminho/absoluto/SchemasXSDgiss
  NFSE_GISS_VALIDAR_SCHEMA=true

No Docker: a pasta deve existir DENTRO do container em
  /var/www/html/storage/SchemasXSDgiss
(rebuild da imagem ou docker compose cp).

A mensagem "Arquivo em desacordo com o XML Schema" vem da PREFEITURA (GISS)
ao validar o XML enviado — nao significa que a pasta de XSD esta vazia.
