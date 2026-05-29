<h1 class="title">Facturación / Ventas</h1>
<p class="subtitle">Módulo de ventas — Maquetación visual</p>

<div class="profile-card">

  <div class="profile-card-header">
    <div class="profile-icon">🧾</div>
    <div>
      <h2>Registrar venta</h2>
      <p>Interfaz de ejemplo para registro de ventas</p>
    </div>
  </div>

  <form class="profile-form">

    <div class="profile-section">
      <h3>Detalles de la venta</h3>

      <div class="profile-grid">
        <div class="form-group">
          <label class="label">Producto</label>
          <select class="input" disabled>
            <option>— Selección deshabilitada —</option>
          </select>
        </div>

        <div class="form-group">
          <label class="label">Cantidad</label>
          <input class="input" type="number" placeholder="0" disabled>
        </div>
      </div>

      <div class="profile-grid">
        <div class="form-group">
          <label class="label">Precio unitario</label>
          <input class="input" type="number" placeholder="$0.00" disabled>
        </div>

        <div class="form-group">
          <label class="label">Medio de pago</label>
          <select class="input" disabled>
            <option>Efectivo</option>
            <option>Débito</option>
            <option>Crédito</option>
            <option>Transferencia</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="label">Total</label>
        <input class="input" placeholder="$0.00" disabled>
      </div>

    </div>

    <div class="profile-actions">
      <button class="btn" type="button" disabled>Registrar venta (maqueta)</button>
    </div>

  </form>

</div>

<div class="profile-card" style="margin-top:20px;">
  <div class="profile-card-header">
    <div class="profile-icon">📊</div>
    <div>
      <h2>Ventas recientes (maquetación)</h2>
    </div>
  </div>

  <table class="result-table">
    <tr>
      <th>Fecha</th>
      <th>Producto</th>
      <th>Cantidad</th>
      <th>Medio de pago</th>
      <th>Total</th>
    </tr>

    <tr>
      <td>2025-11-01 10:30</td>
      <td>Ejemplo producto</td>
      <td>2</td>
      <td>Efectivo</td>
      <td>$5000</td>
    </tr>

    <tr>
      <td>2025-11-01 11:00</td>
      <td>Ejemplo producto 2</td>
      <td>1</td>
      <td>Tarjeta</td>
      <td>$3000</td>
    </tr>

  </table>
</div>
