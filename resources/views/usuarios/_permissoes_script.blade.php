<script>
    function usuarioPermissoesForm(permissoesIniciais, papeisIniciais, permissoesPorPapel) {
        return {
            permissoesSelecionadas: Array.isArray(permissoesIniciais) ? [...permissoesIniciais] : [],
            papeisSelecionados: Array.isArray(papeisIniciais) ? [...papeisIniciais] : [],
            permissoesPorPapel: permissoesPorPapel || {},

            togglePermissao(nome, marcado) {
                if (marcado) {
                    if (! this.permissoesSelecionadas.includes(nome)) {
                        this.permissoesSelecionadas.push(nome);
                    }
                    return;
                }
                this.permissoesSelecionadas = this.permissoesSelecionadas.filter((p) => p !== nome);
                this.removerPapeisIncompativeis();
            },

            moduloCompleto(perms) {
                return perms.every((p) => this.permissoesSelecionadas.includes(p));
            },

            alternarModulo(perms, marcado) {
                if (marcado) {
                    perms.forEach((p) => {
                        if (! this.permissoesSelecionadas.includes(p)) {
                            this.permissoesSelecionadas.push(p);
                        }
                    });
                    return;
                }
                this.permissoesSelecionadas = this.permissoesSelecionadas.filter((p) => ! perms.includes(p));
                this.removerPapeisIncompativeis();
            },

            marcarTodosModulos() {
                const todas = this.permissoesPorPapel.admin
                    || Object.values(this.permissoesPorPapel).flat();
                this.permissoesSelecionadas = [...new Set(todas)];
            },

            limparModulos() {
                this.permissoesSelecionadas = [];
                this.removerPapeisIncompativeis();
            },

            removerPapeisIncompativeis() {
                this.papeisSelecionados = this.papeisSelecionados.filter((papel) => {
                    const pacote = this.permissoesPorPapel[papel] || [];
                    return pacote.every((p) => this.permissoesSelecionadas.includes(p));
                });
            },

            recomputarPermissoesDosPapeis() {
                const uniao = [];
                this.papeisSelecionados.forEach((papel) => {
                    (this.permissoesPorPapel[papel] || []).forEach((p) => {
                        if (! uniao.includes(p)) uniao.push(p);
                    });
                });
                this.permissoesSelecionadas = uniao;
            },

            togglePapel(nome, marcado) {
                if (marcado) {
                    if (! this.papeisSelecionados.includes(nome)) {
                        this.papeisSelecionados.push(nome);
                    }
                } else {
                    this.papeisSelecionados = this.papeisSelecionados.filter((p) => p !== nome);
                }
                this.recomputarPermissoesDosPapeis();
            },
        };
    }
</script>
