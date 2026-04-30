const escenario = document.querySelector('#mi-escenario');

escenario.addEventListener('click', (e) => {

    const rect = escenario.getBoundingClientRect();

    const xPct = ((e.clientX - rect.left) / rect.width) * 100;
    const yPct = ((e.clientY - rect.top) / rect.height) * 100;

    console.log(`Clic en X: ${xPct.toFixed(2)}%, Y: ${yPct.toFixed(2)}%`);

    verificarInteraccion(xPct, yPct);
});
