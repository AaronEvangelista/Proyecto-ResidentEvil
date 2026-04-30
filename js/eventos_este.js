function verificarInteraccion(x, y) {
    // 1. CAJA FUERTE (Centro al fondo)
    if (x > 17.0 && x < 29.0 && y > 65.0 && y < 85.0) {
        lanzarPuzzleCaja();
    }
}