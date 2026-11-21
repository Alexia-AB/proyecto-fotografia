
(function () {
    const form = document.getElementById('form-solicitud');
    if (!form) return;

    const checks   = Array.from(form.querySelectorAll('.chk-servicio'));
    const lista    = document.getElementById('resumen-lista');
    const vacio    = document.getElementById('resumen-vacio');
    const totalEl  = document.getElementById('resumen-total');
    const btn      = document.getElementById('btn-solicitar');
    const formato  = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    function inputDe(chk) {
        return document.getElementById('cant-' + chk.value);
    }

    // Cantidad entera entre 1 y 99
    function cantidadValida(inp) {
        let n = parseInt(inp.value, 10);
        if (isNaN(n) || n < 1) n = 1;
        if (n > 99) n = 99;
        return n;
    }

    // Redibuja el resumen según lo seleccionado
    function actualizar() {
        lista.innerHTML = '';
        let total = 0;
        let cuantos = 0;

        checks.forEach(function (chk) {
            const inp  = inputDe(chk);
            const card = chk.closest('.servicio-card');
            const sel  = chk.checked;

            inp.disabled = !sel;                       // sin seleccionar no se envía
            card.classList.toggle('servicio-seleccionado', sel);
            if (!sel) return;

            const cant     = cantidadValida(inp);
            const subtotal = parseFloat(chk.dataset.precio) * cant;
            total += subtotal;
            cuantos++;

            const li = document.createElement('li');
            li.className = 'resumen-item';

            const info = document.createElement('div');
            const nombre = document.createElement('strong');
            nombre.textContent = chk.dataset.nombre;           // textContent: evita inyectar HTML
            const detalle = document.createElement('span');
            detalle.textContent = cant + ' × ' + formato.format(parseFloat(chk.dataset.precio))
                                + ' = ' + formato.format(subtotal);
            info.appendChild(nombre);
            info.appendChild(detalle);

            // GL06: quitar de la selección
            const quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'resumen-quitar';
            quitar.setAttribute('aria-label', 'Quitar ' + chk.dataset.nombre);
            quitar.textContent = '×';
            quitar.addEventListener('click', function () {
                chk.checked = false;
                actualizar();
            });

            li.appendChild(info);
            li.appendChild(quitar);
            lista.appendChild(li);
        });

        vacio.hidden = cuantos > 0;
        totalEl.textContent = formato.format(total);
        btn.disabled = (cuantos === 0);
        btn.textContent = cuantos > 1 ? 'Solicitar ' + cuantos + ' servicios' : 'Solicitar';
    }

    form.addEventListener('change', function (e) {
        if (e.target.classList.contains('inp-cantidad')) {
            e.target.value = cantidadValida(e.target);        // corrige valores fuera de rango
        }
        actualizar();
    });
    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('inp-cantidad')) actualizar();
    });

    // Seguridad extra: no se envía el formulario sin servicios elegidos
    form.addEventListener('submit', function (e) {
        if (!checks.some(function (c) { return c.checked; })) {
            e.preventDefault();
        }
    });

    actualizar();
})();