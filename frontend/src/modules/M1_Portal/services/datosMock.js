// RESPUESTAS DE EJEMPLO para el prototipo (no provienen del servidor ni del núcleo).
// Los errores siguen el formato común del contrato de M1 (contracts/m1-portal/v1/openapi.yaml):
// { error: { codigo, mensaje, detalles } }.

// 201 de POST /api/m1/v1/clientes (esquema ClienteId).
export const registroExitoso = { id_cliente: 101 };

// 409 de POST /api/m1/v1/clientes (respuesta CorreoDuplicado, RN-M1-14).
export const correoDuplicado = {
  error: { codigo: 'ERR-M1-001', mensaje: 'Ya existe una cuenta con ese correo.', detalles: null },
};

// El ingreso lo resuelve el núcleo y su contrato todavía no define un código para este caso.
export const credencialesIncorrectas = {
  error: { codigo: null, mensaje: 'El correo o la contraseña no son correctos.', detalles: null },
};
