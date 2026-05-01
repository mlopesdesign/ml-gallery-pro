# ML Gallery Pro

Galerias e álbuns profissionais para WordPress com painel dedicado, AJAX e estrutura escalável.

## Versão Atual

**v0.26.0**

## Requisitos

- WordPress 5.8+
- PHP 7.4+

## Instalação

1. Baixe o ZIP da [última release](../../releases/latest)
2. WordPress Admin → Plugins → Adicionar Novo → Upload de Plugin
3. Selecione o arquivo `ML-Gallery-Pro-vX_Y_Z.zip`
4. Ative o plugin

## Atualização

Mesmo processo da instalação — o WordPress sobrescreve a versão anterior automaticamente.

## Estrutura

```
ml-gallery-pro/
├── ml-gallery-pro.php          # Main plugin file
├── readme.txt                  # WordPress.org readme
├── AGENT_RULES.md              # Agent/dev rules + packaging standard
├── assets/
│   ├── css/                    # Admin + frontend stylesheets
│   ├── js/                     # Admin + frontend scripts
│   └── images/                 # Logo, icons
└── includes/
    ├── Admin/                  # Admin UI + AJAX handlers
    ├── Blocks/                 # Gutenberg block
    ├── Core/                   # Plugin bootstrap
    ├── Database/               # Schema, Repository, migrations
    ├── Frontend/               # Shortcodes + templates
    ├── License/                # License manager
    └── Media/                  # Storage engine
```

## Versionamento

Toda release segue o padrão [SemVer](https://semver.org/):

| Tipo | Quando |
|---|---|
| **Patch** (0.26.0 → 0.26.1) | Bug fix, CSS tweak |
| **Minor** (0.26.x → 0.27.0) | Nova funcionalidade |
| **Major** (0.x.y → 1.0.0) | Breaking change |

A versão DEVE coincidir em cinco lugares:

1. Plugin header (`Version:`)
2. Constante PHP (`MLGP_VERSION`)
3. `readme.txt` (`Stable tag:`)
4. Git tag (`vX.Y.Z`)
5. Nome do ZIP (`ML-Gallery-Pro-vX_Y_Z.zip`)

## Releases

### Criar uma release

```bash
# 1. Confirmar versão nos 3 arquivos PHP/txt
grep "Version:" ml-gallery-pro.php
grep "MLGP_VERSION" ml-gallery-pro.php
grep "Stable tag" readme.txt

# 2. Commit e tag
git add -A
git commit -m "release: v0.26.0"
git tag v0.26.0
git push origin main --tags

# 3. Criar ZIP (via GitHub Actions ou manual)
cd .. && zip -r ML-Gallery-Pro-v0_26_0.zip ml-gallery-pro/ \
  -x "ml-gallery-pro/.git/*" \
  -x "ml-gallery-pro/.gitignore" \
  -x "ml-gallery-pro/AGENT_RULES.md" \
  -x "ml-gallery-pro/README.md"
```

### ZIP naming

`ML-Gallery-Pro-v{MAJOR}_{MINOR}_{PATCH}.zip`

Exemplo: `ML-Gallery-Pro-v0_26_0.zip`

## Changelog

### v0.26.0
- Data de publicação editável em galerias e álbuns
- Datas de criação e modificação exibidas no editor
- Galerias adicionadas a álbuns entram no topo

### v0.25.x
- Normalização de thumbnails admin
- Filtro por álbum na tela de galerias
- Fix freeze na busca de álbuns
- Manager de imagens com modo Visual/Avançado
- Grid de thumbnails visual com seleção de capa
- Fix do botão Editar com filtros ativos
- Fix do botão Voltar preservando filtros

### v0.24.x
- Escaneamento de pasta de armazenamento via FTP
- Dropdown com listagem de pastas físicas
- Dois botões independentes: aplicar config a galerias/álbuns
- Fix regenerar previews

## Licença

Proprietário — mlopesdesign.com
