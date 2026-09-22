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
            },

            moduloCompleto(perms) {
                return perms.every((p) => this.permissoesSelecionadas.includes(p));
            },

            alternarModulo(perms, marcado) {
                perms.forEach((p) => this.togglePermissao(p, marcado));
            },

            marcarTodosModulos() {
                const todas = this.permissoesPorPapel.admin
                    || Object.values(this.permissoesPorPapel).flat();
                this.permissoesSelecionadas = [...new Set(todas)];
            },

            limparModulos() {
                this.permissoesSelecionadas = [];
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
