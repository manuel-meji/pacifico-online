// Doble de datos de M4 para el prototipo. Respeta cuerpos, respuestas y códigos del contrato v1.2.0.
// M4 no expone rutas HTTP (despliegue mínimo): en el PC4 este doble se sustituye por props y acciones de Inertia
// que entregan controladores Laravel que llaman a InventarioModuleInterface, sin cambiar las vistas.
// Todo el estado vive en memoria del navegador: el doble no llama a los dobles PHP de la sección 9 ni al servicio
// real; solo usa sus mismos datos y reglas.
// `simular` fuerza alternativas durante la validación: 'suspendido' (ERR-M4-015) y 'm2' (ERR-006).
import { autorizaciones, kardexInicial, pedidos, reservasIniciales } from './datosMock';
import { errorContrato } from './errores';

const espera = (ms = 450) => new Promise((r) => setTimeout(r, ms));

// Usuario de la sesión (mismo id que NucleoDobleM4 de la sección 9). Las llamadas de M5 se asientan con el usuario 0.
const ID_USUARIO = 1;
const ID_USUARIO_M5 = 0;
const ID_COMERCIO = 104;

// Precisión de la sección 6.5: costos numeric(14,4) y montos numeric(16,2). Nunca se redondea a colones enteros.
export const redondear = (n, decimales) => Math.round((n + Number.EPSILON) * 10 ** decimales) / 10 ** decimales;
const costo4 = (n) => redondear(n, 4);
const monto2 = (n) => redondear(n, 2);

export const disponible = (e) => e.cantidad_fisica - e.cantidad_reservada - e.cantidad_defectuosa;

const nuevoUuid = () => crypto.randomUUID?.()
  ?? 'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, () => Math.floor(Math.random() * 16).toString(16));

// El contrato no fija el orden de consultarKardex; el doble lo entrega del más reciente al más antiguo para que
// la primera página tenga lo último que pasó (supuesto del prototipo, a confirmar en el contrato).
const masReciente = (a, b) => (b.fecha_movimiento.localeCompare(a.fecha_movimiento)) || (b.id_kardex - a.id_kardex);

export function crearApi({ simular = null } = {}) {
  let kardex = [...kardexInicial];
  let siguienteId = 2000;
  const reservas = reservasIniciales.map((r) => ({ ...r }));
  // Autorizaciones ya consumidas, como AutorizacionesFake::marcarUsada() (sección 9).
  const usadas = new Set();

  function validarComercio() {
    if (simular === 'm2') throw errorContrato('ERR-006', { modulo: 'M2', motivo: 'no_disponible' });
    if (simular === 'suspendido') throw errorContrato('ERR-M4-015');
  }

  // Misma secuencia de motivos que AutorizacionesFake::verificar(): ERR-002 (403) con el motivo en el mensaje.
  function verificarAutorizacion(id, operacion, idVariacion, idsUbicacion, cantidad) {
    const a = autorizaciones[id];
    const motivo = !a ? 'La autorización no existe.'
      : usadas.has(id) ? 'La autorización ya fue utilizada.'
        : new Date() > new Date(a.vence) ? 'La autorización venció.'
          : a.id_comercio !== ID_COMERCIO ? 'La autorización es de otro comercio.'
            : (a.operacion !== operacion || a.id_variacion !== idVariacion || a.cantidad !== cantidad
              || a.ubicaciones.join() !== idsUbicacion.join()) ? 'La autorización no corresponde a la operación solicitada.'
              : null;
    if (motivo) throw errorContrato('ERR-002', { id_autorizacion: id }, motivo);
    return { id_autorizador: a.id_autorizador, fecha_autorizacion: a.fecha_autorizacion };
  }

  // Asienta el movimiento: devuelve el esquema Movimiento del contrato y agrega la LineaKardex correspondiente.
  function asentar(e, datos) {
    const id = ++siguienteId;
    const ahora = new Date().toISOString();
    const movimiento = {
      id_movimiento: id, id_existencia: e.id_existencia, id_comercio: e.id_comercio, id_producto: e.id_producto,
      id_variacion: e.id_variacion, id_ubicacion: e.id_ubicacion, tipo_movimiento: datos.tipo_movimiento,
      efecto_stock: datos.efecto_stock, cantidad: datos.cantidad, costo_unitario: datos.costo_unitario ?? null,
      id_usuario: datos.id_usuario ?? ID_USUARIO, referencia_tipo: datos.referencia_tipo ?? null, referencia_id: datos.referencia_id ?? null,
      justificacion: datos.justificacion, fecha_movimiento: ahora,
      id_autorizador: datos.id_autorizador ?? null, fecha_autorizacion: datos.fecha_autorizacion ?? null,
      saldo_reservado: e.cantidad_reservada, saldo_disponible: disponible(e),
    };
    kardex = [...kardex, {
      ...movimiento, id_kardex: id, metodo_valorizacion: 'promedio_ponderado',
      monto_total: datos.costo_unitario == null ? null : monto2(datos.costo_unitario * datos.cantidad),
      saldo_cantidad: e.cantidad_fisica, saldo_valorizado: monto2(e.cantidad_fisica * e.costo_promedio), fecha_registro: ahora,
    }];
    return movimiento;
  }

  // --- Reservas (CU-M4-06 y CU-M4-07). Las invoca M5 por InventarioModuleInterface; no hay pantalla de M4 que las cree.
  const existenciaDe = (existencias, r) => existencias.find((x) => x.id_existencia === r.id_existencia);

  function buscarReserva(id) {
    const r = reservas.find((x) => x.id_reserva === id);
    if (!r) throw errorContrato('ERR-M4-004');
    if (r.id_comercio !== ID_COMERCIO) throw errorContrato('ERR-003');
    return r;
  }

  // Lo que hace el job de vencimiento: libera una sola vez las ACTIVA cuyo plazo pasó.
  function vencerReservas(existencias) {
    const ahora = new Date();
    for (const r of reservas) {
      if (r.estado_reserva !== 'activa' || new Date(r.fecha_expiracion) > ahora) continue;
      const e = existenciaDe(existencias, r);
      e.cantidad_reservada -= r.cantidad;
      Object.assign(r, { estado_reserva: 'vencida', fecha_resolucion: ahora.toISOString(), motivo_cierre: 'vencimiento' });
      asentar(e, { tipo_movimiento: 'vencimiento_reserva', efecto_stock: 'sin_cambio_fisico', cantidad: r.cantidad, id_usuario: ID_USUARIO_M5,
        referencia_tipo: 'reserva', referencia_id: r.id_reserva, justificacion: 'Venció el plazo de la reserva sin confirmación' });
    }
  }

  function validarVinculo(vinculo) {
    const p = pedidos.find((x) => x.id_pedido === vinculo.id_pedido);
    if (!p || p.id_subpedido !== vinculo.id_subpedido) throw errorContrato('ERR-M4-007');
    return p;
  }

  return {
    // Doble de ObtenerComercioActivo de M2 (v1.2.1), que M4 consulta antes de registrar movimientos (RN-M4-009).
    // Con el comercio no activo, el contrato responde ERR-M4-015 al registrar; aquí se consulta al abrir la pantalla (H9).
    async consultarEstadoComercio() {
      await espera(150);
      if (simular === 'm2') throw errorContrato('ERR-006', { modulo: 'M2', motivo: 'no_disponible' });
      if (simular === 'suspendido') return { id_comercio: ID_COMERCIO, estado: 'Suspendido', activo: false, motivo_estado: 'Membresía vencida' }; // forma de ComerciosDependencia::obtenerComercioActivo
      return { id_comercio: ID_COMERCIO, estado: 'Activo', activo: true, motivo_estado: null };
    },

    // GET /kardex?id_comercio&id_variacion&id_ubicacion&pagina&por_pagina → KardexRespuesta { datos, paginacion }
    async consultarKardex({ id_variacion, id_ubicacion, pagina = 1, por_pagina = 50 }) {
      await espera(250);
      const filtradas = kardex
        .filter((k) => k.id_variacion === id_variacion && (!id_ubicacion || k.id_ubicacion === id_ubicacion))
        .sort(masReciente);
      const total = filtradas.length;
      return {
        datos: filtradas.slice((pagina - 1) * por_pagina, pagina * por_pagina),
        paginacion: { pagina, por_pagina, total, total_paginas: Math.max(1, Math.ceil(total / por_pagina)) },
      };
    },

    // POST /movimientos (MovimientoSolicitud: entrada exige costo_unitario). 201 Movimiento.
    async registrarMovimientoManual(existencias, solicitud) {
      await espera();
      validarComercio();
      const e = existencias.find((x) => x.id_variacion === solicitud.id_variacion && x.id_ubicacion === solicitud.id_ubicacion);
      if (!e) throw errorContrato('ERR-M4-014');
      const entrada = solicitud.tipo_movimiento === 'entrada';
      if (!entrada && solicitud.cantidad > disponible(e)) throw errorContrato('ERR-M4-001');

      let costo = e.costo_promedio;
      if (entrada) {
        const valorAnterior = e.cantidad_fisica * e.costo_promedio;
        e.cantidad_fisica += solicitud.cantidad;
        e.costo_promedio = costo4((valorAnterior + solicitud.cantidad * solicitud.costo_unitario) / e.cantidad_fisica);
        costo = solicitud.costo_unitario;
      } else {
        e.cantidad_fisica -= solicitud.cantidad;
      }
      return asentar(e, {
        tipo_movimiento: solicitud.tipo_movimiento, efecto_stock: entrada ? 'aumenta' : 'disminuye',
        cantidad: solicitud.cantidad, costo_unitario: costo, justificacion: solicitud.justificacion,
      });
    },

    // POST /transferencias (TransferenciaSolicitud). 201 TransferenciaRespuesta con dos Movimiento.
    async registrarTransferencia(existencias, s) {
      await espera();
      validarComercio();
      const aut = verificarAutorizacion(s.id_autorizacion, 'transferencia', s.id_variacion,
        [s.id_ubicacion_origen, s.id_ubicacion_destino], s.cantidad);
      const origen = existencias.find((x) => x.id_variacion === s.id_variacion && x.id_ubicacion === s.id_ubicacion_origen);
      const destino = existencias.find((x) => x.id_variacion === s.id_variacion && x.id_ubicacion === s.id_ubicacion_destino);
      if (!origen || !destino) throw errorContrato('ERR-M4-014');
      if (s.cantidad > disponible(origen)) throw errorContrato('ERR-M4-001');

      const costo = origen.costo_promedio;
      origen.cantidad_fisica -= s.cantidad;
      const valorDestino = destino.cantidad_fisica * destino.costo_promedio + s.cantidad * costo;
      destino.cantidad_fisica += s.cantidad;
      destino.costo_promedio = costo4(valorDestino / destino.cantidad_fisica);
      const id_transferencia = nuevoUuid();
      const comun = { cantidad: s.cantidad, costo_unitario: costo, justificacion: s.justificacion, referencia_tipo: 'transferencia', referencia_id: id_transferencia, ...aut };
      usadas.add(s.id_autorizacion);
      return {
        id_transferencia,
        movimientos: [
          asentar(origen, { ...comun, tipo_movimiento: 'transferencia_salida', efecto_stock: 'disminuye' }),
          asentar(destino, { ...comun, tipo_movimiento: 'transferencia_entrada', efecto_stock: 'aumenta' }),
        ],
      };
    },

    // Solo del doble: las reservas del comercio en esta sesión, para el panel de reservas. En el PC4 la vista
    // recibe esta lista por props de Inertia (consulta de reservas del comercio, a agregar al contrato).
    async listarReservas(existencias) {
      await espera(150);
      vencerReservas(existencias);
      return [...reservas].sort((a, b) => b.fecha_creacion.localeCompare(a.fecha_creacion));
    },

    // POST /reservas (ReservaSolicitud) → 201 Reserva. Llamada de M5 al crear el pedido (CU-M4-06).
    async reservarExistencias(existencias, s) {
      await espera();
      validarComercio();
      vencerReservas(existencias);
      const candidatas = existencias.filter((x) => x.id_variacion === s.id_variacion && (!s.id_ubicacion || x.id_ubicacion === s.id_ubicacion));
      if (s.id_ubicacion && candidatas.length === 0) throw errorContrato('ERR-M4-002');
      // Sin compromiso parcial: una sola ubicación con toda la cantidad.
      const e = candidatas.find((x) => disponible(x) >= s.cantidad);
      if (!e) throw errorContrato('ERR-M4-001');
      e.cantidad_reservada += s.cantidad;
      const ahora = new Date();
      const r = {
        id_reserva: nuevoUuid(), id_comercio: ID_COMERCIO, id_existencia: e.id_existencia, id_variacion: e.id_variacion,
        id_ubicacion: e.id_ubicacion, cantidad: s.cantidad, estado_reserva: 'activa', id_pedido: null, id_subpedido: null,
        fecha_creacion: ahora.toISOString(), fecha_expiracion: new Date(ahora.getTime() + (s.expiracion_minutos ?? 15) * 60000).toISOString(),
        fecha_resolucion: null, motivo_cierre: null,
      };
      reservas.push(r);
      asentar(e, { tipo_movimiento: 'reserva', efecto_stock: 'sin_cambio_fisico', cantidad: s.cantidad, id_usuario: ID_USUARIO_M5,
        referencia_tipo: 'reserva', referencia_id: r.id_reserva, justificacion: 'Reserva de M5 para un carrito en checkout' });
      return { ...r };
    },

    // POST /reservas/{id}/confirmar (ReservaVinculoSolicitud) → 200 Reserva. ACTIVA → CONFIRMADA (CU-M4-07).
    async confirmarReserva(existencias, id, vinculo) {
      await espera();
      vencerReservas(existencias);
      const r = buscarReserva(id);
      validarVinculo(vinculo);
      if (r.estado_reserva === 'confirmada') {
        if (r.id_pedido === vinculo.id_pedido && r.id_subpedido === vinculo.id_subpedido) return { ...r }; // repetición idempotente
        throw errorContrato('ERR-M4-007');
      }
      if (r.estado_reserva === 'vencida') throw errorContrato('ERR-M4-005');
      if (r.estado_reserva !== 'activa') throw errorContrato('ERR-M4-006');
      Object.assign(r, { estado_reserva: 'confirmada', id_pedido: vinculo.id_pedido, id_subpedido: vinculo.id_subpedido });
      asentar(existenciaDe(existencias, r), { tipo_movimiento: 'confirmacion_reserva', efecto_stock: 'sin_cambio_fisico', cantidad: r.cantidad,
        id_usuario: ID_USUARIO_M5, referencia_tipo: 'reserva', referencia_id: r.id_reserva,
        justificacion: `M5 confirmó la reserva: pedido ${vinculo.id_pedido}, subpedido ${vinculo.id_subpedido}` });
      return { ...r };
    },

    // POST /reservas/{id}/liberar (LiberarReservaSolicitud) → 200 Reserva. ACTIVA o CONFIRMADA → LIBERADA (CU-M4-07).
    async liberarReserva(existencias, id, { motivo }) {
      await espera();
      vencerReservas(existencias);
      const r = buscarReserva(id);
      if (r.estado_reserva === 'consumida') throw errorContrato('ERR-M4-008');
      if (r.estado_reserva === 'liberada' || r.estado_reserva === 'vencida') return { ...r }; // ya cerrada: sin efectos
      const e = existenciaDe(existencias, r);
      e.cantidad_reservada -= r.cantidad;
      Object.assign(r, { estado_reserva: 'liberada', fecha_resolucion: new Date().toISOString(), motivo_cierre: motivo });
      asentar(e, { tipo_movimiento: 'liberacion_reserva', efecto_stock: 'sin_cambio_fisico', cantidad: r.cantidad, id_usuario: ID_USUARIO_M5,
        referencia_tipo: 'reserva', referencia_id: r.id_reserva, justificacion: `M5 liberó la reserva (${motivo.replaceAll('_', ' ')})` });
      return { ...r };
    },

    // POST /reservas/{id}/salida (ReservaVinculoSolicitud) → 201 SalidaReserva. CONFIRMADA → CONSUMIDA (despacho).
    async confirmarSalidaPorReserva(existencias, id, vinculo) {
      await espera();
      validarComercio();
      vencerReservas(existencias);
      const r = buscarReserva(id);
      if (r.estado_reserva === 'vencida') throw errorContrato('ERR-M4-005');
      if (r.estado_reserva !== 'confirmada') throw errorContrato('ERR-M4-006');
      const p = validarVinculo(vinculo);
      if (r.id_pedido !== vinculo.id_pedido || r.id_subpedido !== vinculo.id_subpedido) throw errorContrato('ERR-M4-007');
      if (p.estado_pedido !== 'pagado') throw errorContrato('ERR-M4-009');
      const e = existenciaDe(existencias, r);
      e.cantidad_fisica -= r.cantidad;
      e.cantidad_reservada -= r.cantidad;
      Object.assign(r, { estado_reserva: 'consumida', fecha_resolucion: new Date().toISOString(), motivo_cierre: 'despacho' });
      const m = asentar(e, { tipo_movimiento: 'salida', efecto_stock: 'disminuye', cantidad: r.cantidad, costo_unitario: e.costo_promedio,
        id_usuario: ID_USUARIO_M5, referencia_tipo: 'reserva', referencia_id: r.id_reserva,
        justificacion: `Despacho del subpedido ${r.id_subpedido} (pedido ${r.id_pedido})` });
      return {
        id_movimiento: m.id_movimiento, id_kardex: m.id_movimiento, id_reserva: r.id_reserva, id_comercio: ID_COMERCIO,
        id_variacion: r.id_variacion, cantidad: r.cantidad, costo_unitario: m.costo_unitario,
        monto_total: monto2(m.costo_unitario * r.cantidad), fecha_movimiento: m.fecha_movimiento,
      };
    },
  };
}
