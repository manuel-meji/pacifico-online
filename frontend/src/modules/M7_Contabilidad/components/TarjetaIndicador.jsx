
export default function TarjetaIndicador({
  titulo,
  valor,
  detalle,
  color = 'azul',
}) {
  return (
    <article className="m7-card m7-kpi">
      <p className="m7-kpi-title">{titulo}</p>

      <p className={`m7-kpi-value ${color}`}>
        {valor}
      </p>

      <p className="m7-muted">{detalle}</p>
    </article>
  );
}
