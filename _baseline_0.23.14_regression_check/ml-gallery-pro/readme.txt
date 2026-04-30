=== ML Gallery Pro ===
Contributors: mlopesdesign
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.23.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin comercial para galerias e albuns com painel proprio, AJAX e estrutura escalavel.

== Description ==

ML Gallery Pro nasce com base instalavel profissional:

* tabelas proprias para galerias, albuns e itens
* menu admin dedicado
* CRUD inicial via AJAX
* criacao de galerias com upload direto e shortcode automatico
* configuracoes globais
* shortcode base para galerias e albuns
* display types base para grid, slideshow e image browser
* tags por imagem com catalogo admin e shortcode global por tag

== Shortcodes ==

Use:

`[ml_gallery id="1"]`

ou:

`[ml_gallery type="album" id="1"]`

ou:

`[ml_gallery type="tag" tag="evento"]`

ou:

`[ml_tag_gallery tag="evento"]`

Compatibilidade legada:

`[ml_gallery_pro gallery="1"]`

ou:

`[ml_gallery_pro album="1"]`

== Changelog ==

= 0.23.14 =
* confirma as queries administrativas de galerias e albuns com apenas um ORDER BY dinamico por SQL
* mantem a ordenacao persistente sem alterar frontend, Grid, Grid Plus, AJAX ou estrutura de banco

= 0.23.13 =
* adiciona ordenacao persistente por SQL para as listas administrativas de galerias e albuns
* sincroniza versionamento do cabecalho, constante interna e readme
* remove codigo de debug do admin que chamava acoes AJAX inexistentes
* corrige restauracao de texto dos botoes destrutivos no admin
* torna a limpeza de diretorios antigos mais restrita e preserva a licenca no reset de fabrica

= 0.22.92 =
* Isola Grid Plus apenas no print de álbum e restaura o fluxo original dos layouts existentes.


= 0.22.67 =

* reorganiza a listagem de Gerenciar galerias para o mesmo padrao visual de Albuns, com uma galeria por linha
* ajusta thumb, bloco de informacoes e acoes para evitar estouro e melhorar leitura da lista
* adiciona edicao em lote de ALT no manager de imagens, com substituir e limpar



= 0.22.25 =

* adiciona acao explicita para excluir todas as galerias com limpeza completa das imagens vinculadas
* adiciona acao explicita para excluir todas as imagens mantendo a estrutura das galerias
* expõe a zona de limpeza total na aba Ferramentas das configuracoes com reset de fabrica visivel

= 0.22.13 =

* corrige a integracao com a License Hub para enviar os campos reais esperados pelo servidor: domain, site_url, home_url, admin_email e site_fingerprint
* ajusta a validacao para ativar no servidor com bind correto do site e corrige a desativacao para comunicar a Hub antes de limpar o estado local
* preserva o slug canonico `ml-gallery-pro` no pacote final desta atualizacao, evitando repetir os fantasmas causados por empacotamentos antigos fora do slug oficial

= 0.22.12 =

* cria o modulo de licenca/serial inspirado no ML Media Optimizer com painel proprio e chamadas AJAX dedicadas
* conecta o HUD de validacao ao estado da licenca (plano Free/Full + mensagens) e expõe o mesmo status no dashboard
* protege acoes premium da aba de validacao via `is_full_license_active()` e libera a UI completa apenas apos validacao

= 0.22.11 =

* reduz a escala tipografica do hero e dos destaques do dashboard para um admin mais equilibrado
* conecta a HUD do dashboard ao relatorio real de validacao no carregamento inicial
* mantém a leitura comercial do painel sem deixar a validacao solta do estado da base

= 0.22.10 =

* refina o dashboard com entrada mais comercial, leitura de saude da base e proximos passos mais claros
* destaca validacao, colecoes e fluxo guiado como acoes principais do painel
* melhora a hierarquia visual do admin com novos cards de contexto e apresentacao mais curta do produto

= 0.22.9 =

* remove o visual de botao dos shortcodes no admin e no editor, mantendo apenas o shortcode clicavel para copiar
* melhora o alinhamento das linhas de galerias e albuns recentes para um acabamento mais profissional
* redistribui o card do manager de imagens com coluna lateral de acoes e campos mais compactos, reduzindo a altura visual do container

= 0.22.8 =

* corrige a linha de atualizacao acima da 0.22.4 com versao interna sincronizada entre cabecalho e assets
* reempacota o plugin em formato seguro para WordPress, com estrutura interna padrao `ml-gallery-pro/`
* preserva a base funcional da 0.22.4 como ponto confiavel para a proxima rodada de ajustes visuais

= 0.22.4 =

* suaviza a interface do admin com shortcodes mais clean, sem fundo preto pesado, e copia direta ao clicar no proprio shortcode
* reduz o peso visual do card de storage no dashboard e na tela de galerias, evitando caminho gigante com cara de metrica quebrada
* baixa o tamanho padrao dos titulos principais para um baseline mais limpo e comercial em galerias e albuns

= 0.22.3 =

* corrige a grade de albuns para que a largura configurada da capa de exibicao passe a controlar de verdade o tamanho visual do card no frontend
* melhora o comportamento da colecao de albuns em desktop, tablet e mobile, respeitando melhor as dimensoes configuradas

= 0.22.2 =

* torna opcionais o titulo principal e a descricao da galeria, com controle global nas configuracoes e override por galeria no editor
* corrige a persistencia das configuracoes por abas para evitar que campos ocultos zeren outras opcoes ao salvar
* restaura o lightbox como default seguro nas atualizacoes recentes, evitando que ele seja desativado por um save parcial das configuracoes
* melhora o frontend de albuns para respeitar melhor largura e altura configuradas da capa de exibicao

= 0.22.1 =

* corrige o empacotamento do ZIP para distribuicao em servidor Linux, garantindo estrutura interna padrao `ml-gallery-pro/` com caminhos compatveis com o WordPress
* mantem as melhorias da rodada anterior em configuracoes por setores, ocultacao global de titulos e manager mais comercial

= 0.22.0 =

* reorganiza a tela de configuracoes em setores com abas no topo, inspirada no fluxo mais claro de settings do NGG
* adiciona chave global para ocultar titulos em tudo no frontend, incluindo galerias, albuns, cards e origem das galerias por tag
* compacta o manager de imagens com acoes em lote por grupos, deixando a barra mais comercial e mais pratica para acervos grandes
* faz um passe de refinamento em PT-BR nos blocos principais desta rodada

= 0.21.0 =

* melhora a capa de exibicao dos albuns com largura e altura configuraveis no global e por album
* adiciona acoes em lote reais no manager para substituir e limpar titulos e legendas
* reorganiza a barra do manager em blocos mais comerciais para fluxo de acervo grande

= 0.20.3 =

* adiciona controle global para altura padrao e modo de encaixe da capa de exposicao dos albuns
* adiciona override por album no manager para decidir se a capa mostra a imagem inteira ou recorta para preencher
* corrige o frontend dos cards de album para deixar de herdar o crop fixo da galeria e respeitar a nova janela visual configuravel

= 0.20.2 =

* remove a duplicidade da tela de albuns, concentrando a experiencia em lista + manager sem bloco repetido de resumo
* adiciona controles reais de frontend do album no manager, incluindo titulo principal, descricao, titulos dos cards, descricoes, paginacao e cantos arredondados
* limpa os defaults do album para um visual mais comercial, sem titulo/descricao forçados no topo e sem arredondamento ligado por padrao
* corrige a estrutura interna da versao do plugin para manter cabecalho e constante sincronizados

= 0.20.1 =

* desliga por padrao os filtros de busca/tag no frontend e transforma esse recurso em opcional por galeria ou configuracao global
* desliga por padrao a exibicao de titulos, legendas e tags sobre as imagens, deixando tudo isso opcional e controlavel no editor
* adiciona controle global para novos padroes visuais de titulos, legendas e tags das imagens no frontend
* evita que galerias novas nascam com overlay editorial forcado em presets como mosaic, tile e filmstrip

= 0.20.0 =

* refaz o frontend de albuns para sair em colecao/grid de cards, sem hero inflado, com visual mais consistente com o shortcode de galerias
* elimina a duplicidade do breadcrumb na raiz do album, exibindo navegacao apenas quando houver profundidade real na colecao
* reorganiza o manager de imagens com barra de operacoes em lote mais compacta, horizontal e profissional
* remove a busca/filtro visual desnecessaria dessa tela de edicao para manter foco total no gerenciamento das imagens

= 0.19.1 =

* corrige o cadastro de albuns com fallback server-side em `admin-post`, evitando tela branca quando o submit nativo escapar do JavaScript
* adiciona rota segura, nonce e redirect correto de volta para a tela de albuns com o album ja aberto no manager
* reforca tambem o formulario base de galerias com `POST` seguro e endpoint explicito no admin

= 0.19.0 =

* adiciona preset global reutilizavel para novas galerias, aplicado automaticamente no editor a partir das configuracoes do produto
* adiciona filtros de frontend por busca e tag nas galerias em grid, masonry, mosaic, tile e justified
* aplica os filtros no servidor antes da paginacao, preservando navegacao, links e acervos grandes com mais consistencia

= 0.18.0 =

* adiciona bloco nativo do editor do WordPress para inserir `galeria`, `album` ou `tag` sem shortcode manual
* inclui preview dinamico do bloco no editor reaproveitando o motor real de renderizacao do plugin
* adiciona controles principais de layout, navegacao, paginacao, conteudo e tipografia direto no inspector do bloco

= 0.17.0 =

* adiciona `presets visuais por galeria` no editor para acelerar a configuracao comercial dos layouts
* fortalece o frontend de `albuns/colecoes` com hero visual, mosaic de capas e estatisticas da colecao
* prepara o fluxo para evolucoes futuras de presets e temas de exibicao sem quebrar o editor atual

= 0.16.0 =

* adiciona os novos display types `Tile` e `Filmstrip` no editor, na persistencia e no frontend
* melhora a opcao de `setas` no slideshow, removendo tambem as setas da faixa de miniaturas quando desativadas
* amplia a linha visual do frontend com novos layouts mais comerciais para aproximar o produto do ecossistema NGG

= 0.15.1 =

* corrige o `Justified` no responsivo, evitando que as linhas virem faixas muito baixas em telas estreitas
* troca o comportamento mobile do `Justified` para uma grade mais robusta, preservando melhor a leitura das imagens

= 0.15.0 =

* transforma a faixa de miniaturas do `Slideshow` em uma trilha unica horizontal com navegacao lateral, evitando a quebra em varias linhas
* centraliza automaticamente a miniatura ativa dentro da trilha durante a navegacao entre os slides
* adiciona suporte global a PNG personalizado para `seta anterior` e `seta proxima`, mantendo o icone nativo do plugin como fallback

= 0.14.0 =

* corrige o `Slideshow`, impedindo o empilhamento vertical dos slides no frontend
* adiciona controle global e por galeria para exibir ou ocultar `setas` e `miniaturas` no slideshow
* reforca o modo `Mosaic` com tiles mais estaveis e conteudo sobreposto sem bloquear a abertura da imagem

= 0.13.2 =

* corrige o preenchimento dos tiles no modo `Mosaic`, evitando cards vazios e blocos colapsados no frontend real
* reforca a camada de sobreposicao do conteudo no mosaico e elimina renderizacao vazia quando nao ha texto para exibir

= 0.13.1 =

* remove a duplicidade visual da tela de edicao, escondendo cards e listagens enquanto a galeria esta aberta no manager
* limpa a tela `Add Images` durante a edicao para manter foco apenas no editor da galeria

= 0.13.0 =

* adiciona o novo display type `Mosaic` com composicao visual mais proxima de galeria comercial
* melhora o `Masonry` e remove duplicidade visual da tela de galerias quando uma galeria esta aberta no editor
* refaz o `Slideshow` com setas reais de navegacao e trilha de miniaturas clicaveis
* reforca a inicializacao do frontend para casos em que o tema injeta ou re-renderiza galerias apos o carregamento inicial

= 0.12.1 =

* corrige o falso rodape branco dos cards quando titulos e legendas estao desligados no frontend
* melhora o comportamento visual do `Masonry`, removendo a quebra que fazia a galeria parecer grid comum
* adiciona controle global e por galeria para ligar/desligar a paginacao e definir quantas imagens aparecem por pagina
* adiciona controle global e por galeria para tamanho e cor do titulo principal e dos titulos das imagens
* inclui navegacao de colecao em albuns com breadcrumb, abertura de subalbum e abertura de galeria dentro do shortcode do album

= 0.11.0 =

* adiciona os modos de exibicao `Masonry` e `Justified` no editor, no shortcode e no frontend
* adiciona override por galeria para usar cantos arredondados ou vivos, sem depender apenas da configuracao global
* inclui controle por galeria para altura da linha no layout `Justified`
* consolida o frontend para uma linha visual mais comercial, mantendo compatibilidade com lightbox e shortcodes atuais

= 0.10.1 =

* corrige o espaco branco abaixo dos thumbs com renderizacao de imagem mais robusta no frontend
* adiciona opcao global para arredondar ou zerar os cantos das galerias e thumbs
* refina o visual dos cards para um layout mais limpo e mais proximo da linguagem comercial desejada

= 0.10.0 =

* painel de configuracoes ampliado com perfis reais de imagem para thumb, medium e large
* configuracao de watermark textual com opacidade e posicao, integrada ao motor local de geracao
* ferramenta de regeneracao global da biblioteca local a partir das configuracoes atuais
* acoes em lote novas no manager para rotacionar 90 graus para a esquerda ou direita
* rotas AJAX e mensagens operacionais consolidadas para o motor de imagem comercial

= 0.9.3 =

* corrige a persistencia de uploads grandes em lotes, com tratamento mais seguro de nomes de arquivo e retorno de erro mais claro
* impede que a galeria suma da interface quando um lote falha no envio ou no registro
* ajusta consultas de galerias e albuns para listagem segura em ambientes com SQL mais estrito

= 0.9.2 =
* Correcao do upload do computador para galerias grandes, com envio automatico em lotes internos em vez de uma unica requisicao
* Criacao de galeria com centenas ou milhares de imagens agora respeita os limites por requisicao do servidor sem cortar o total importado
* Tratamento de erro aprimorado para informar progresso parcial caso um lote falhe no meio do processo

= 0.9.1 =
* Todos os retornos de sucesso e erro do admin e do manager passaram a usar toast no canto superior direito
* Toasts padronizados com verde para sucesso, vermelho para erro e auto fechamento em 5 segundos

= 0.9.0 =
* Importacao por pasta do servidor em raizes autorizadas, tanto na criacao da galeria quanto no manager da galeria existente
* Acoes em lote ampliadas com adicionar tags, substituir tags, limpar tags e regenerar previews das imagens locais
* Ferramentas operacionais no manager para consolidar fluxo de acervo proprio em `wp-content/ml-gallery` sem depender da midia do WordPress

= 0.8.0 =
* Manager de imagens com filtros por busca, visibilidade e tag dentro da galeria
* Reordenacao por drag and drop no manager, com setas mantidas como fallback
* Paginacao real no frontend para galerias e galerias dinamicas por tag, com suporte a `per_page` e navegacao por pagina

= 0.7.0 =
* Motor de exibicao base com `grid`, `slideshow` e `image browser` no shortcode
* Configuracoes por galeria para colunas, espaco, titulos, legendas e autoplay
* Tags por imagem no manager e catalogo de tags com shortcode global `[ml_gallery type="tag" tag="slug"]`
* Nova aba `Tags` no admin para acompanhar cobertura e reutilizar shortcodes por assunto

= 0.6.0 =
* Manager real de albuns com abertura dedicada, estrutura persistida e shortcode padrao por colecao
* Vinculo de galerias e subalbuns com ordenacao manual e protecao contra ciclos
* Fluxo consolidado para salvar dados do album e estrutura da colecao no mesmo painel

= 0.5.1 =
* Nova tela dedicada `Add Images` no admin para criar galeria e subir imagens no mesmo fluxo
* Remocao da logo no menu lateral e limpeza do cabecalho para evitar duplicidade visual
* Ajustes no manager de galerias para reduzir espacos vazios e concentrar o fluxo principal

= 0.5.0 =
* Importacao de imagens por pasta local no manager da galeria
* Importacao de ZIP no fluxo de criar galeria e no manager existente
* Selecao multipla de imagens com acoes em massa para exibir, ocultar e excluir
* Fluxo admin mais proximo do gerenciador estilo NextGEN, com upload em storage proprio

= 0.4.0 =
* Navegacao por abas em todas as telas do plugin
* Dashboard de galerias refeito com busca, capa e shortcode
* Modal de criacao de galeria com upload multiplo no mesmo fluxo
* Novo shortcode simplificado `[ml_gallery id="123"]`
* Alias para album com `[ml_gallery type="album" id="123"]`

= 0.3.0 =
* Novo storage proprio em diretorio `ml-gallery` dentro de `wp-content`
* Upload AJAX direto de imagens sem usar a biblioteca de midia do WordPress
* Variantes locais para preview e frontend
* Compatibilidade mantida com itens antigos baseados em attachment
* Upgrade automatico de estrutura e banco ao atualizar o plugin

= 0.2.1 =
* Correcao robusta do cadastro de galeria para impedir tela em branco por submit nativo
* Fallback server-side com redirect seguro de volta para a tela de galerias
* Reabertura automatica da galeria apos salvar e entrada imediata no upload
* Atualizacao visual do painel admin para o padrao-base dos plugins ML

= 0.2.0 =
* Editor de galeria com selecao de imagens pela midia do WordPress
* Persistencia de capa, ordenacao e metadados basicos por imagem
* Refinos de frontend para alt, lazy load e capa de itens de album

= 0.1.0 =
* Base inicial instalavel do produto
