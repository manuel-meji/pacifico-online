// Utilidades de presentación para facturas y notas (CU-004, CU-005, CU-006).

export const colones = (n) => `₡${Number(n).toLocaleString('es-CR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export const fecha = (iso) => (iso ? new Date(iso).toLocaleString('es-CR') : '—');

/** Descarga un XML generado por M6 sin pasar por el servidor. */
export function descargarXml(xml, nombre) {
  const url = URL.createObjectURL(new Blob([xml], { type: 'application/xml' }));
  const a = Object.assign(document.createElement('a'), { href: url, download: `${nombre}.xml` });
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

/**
 * Validación en el navegador del cuerpo de una nota (RN-004: monto > 0).
 * El backend vuelve a validar todo; esto solo evita viajes inútiles.
 */
export function validarNota({ monto, motivo }) {
  const errores = {};
  const texto = String(monto ?? '').trim();

  if (texto === '' || Number.isNaN(Number(texto))) {
    errores.monto = 'Indique un monto numérico.';
  } else if (Number(texto) <= 0) {
    errores.monto = 'El monto debe ser mayor que cero.';
  } else if (!/^\d+(\.\d{1,2})?$/.test(texto)) {
    errores.monto = 'Use como máximo dos decimales.';
  }

  if (!String(motivo ?? '').trim()) {
    errores.motivo = 'Indique el motivo de la nota.';
  } else if (String(motivo).trim().length > 200) {
    errores.motivo = 'El motivo admite como máximo 200 caracteres.';
  }

  return { valido: Object.keys(errores).length === 0, errores };
}
