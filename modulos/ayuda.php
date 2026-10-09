<?php
$ayudaRol = htmlspecialchars((string) ($rolNombre ?? 'Usuario'));
?>

<section class="page-heading help-heading">
  <div>
    <p class="eyebrow">Manual del usuario</p>
    <h1 class="title">Ayuda del Sistema FME</h1>
    <p class="subtitle">Guía rápida para aprender a usar los módulos principales del sistema. Algunas opciones pueden variar según tu perfil.</p>
  </div>
</section>

<section class="surface-card help-welcome-card">
  <div class="help-welcome-icon" aria-hidden="true">?</div>
  <div>
    <h2>¿Necesitás ayuda para empezar?</h2>
    <p>Seleccioná una pregunta para desplegar las instrucciones. Tu perfil actual es <strong><?= $ayudaRol ?></strong>, por lo que solo verás en el menú los módulos que tengas habilitados.</p>
  </div>
</section>

<section class="help-faq" aria-label="Preguntas frecuentes del Sistema FME">
  <details class="help-faq-item" open>
    <summary>
      <span class="help-faq-number">1</span>
      <span>¿Cómo uso el Panel de control y la configuración de mi usuario?</span>
      <span class="help-faq-toggle" aria-hidden="true">+</span>
    </summary>
    <div class="help-faq-content">
      <p>El <strong>Panel de control</strong> es la pantalla inicial del sistema. Allí podés consultar un resumen del inventario, productos con stock bajo y accesos rápidos a las funciones habilitadas para tu perfil.</p>
      <ol>
        <li>Usá el menú lateral izquierdo para entrar a cada módulo.</li>
        <li>Revisá los indicadores del panel para detectar productos agotados o con poco stock.</li>
        <li>Desde tu nombre, arriba a la derecha, entrá en <strong>Configuración de usuario</strong> para completar o actualizar tus datos personales.</li>
        <li>Cuando termines de trabajar, utilizá <strong>Cerrar sesión</strong> desde el mismo menú de usuario.</li>
      </ol>
    </div>
  </details>

  <details class="help-faq-item">
    <summary>
      <span class="help-faq-number">2</span>
      <span>¿Cómo administro Productos, Proveedores y Movimientos de stock?</span>
      <span class="help-faq-toggle" aria-hidden="true">+</span>
    </summary>
    <div class="help-faq-content">
      <p>Estos módulos permiten mantener actualizado el inventario y conocer de dónde provienen los productos.</p>
      <ol>
        <li>En <strong>Proveedores</strong>, registrá primero los datos de las empresas o personas que suministran mercadería.</li>
        <li>En <strong>Productos</strong>, cargá nombre, precio, stock inicial, categoría y proveedor. El código de barras es opcional: si lo dejás vacío, FME genera uno automáticamente.</li>
        <li>Usá los filtros de Productos para buscar por nombre, código de barras, estado, categoría, proveedor, precio o stock.</li>
        <li>En <strong>Movimientos</strong>, registrá entradas o salidas de stock cuando corresponda. Indicá el producto, cantidad y motivo para mantener la trazabilidad del inventario.</li>
      </ol>
      <div class="help-tip"><strong>Consejo:</strong> antes de registrar una salida manual, verificá que exista stock suficiente del producto.</div>
    </div>
  </details>

  <details class="help-faq-item">
    <summary>
      <span class="help-faq-number">3</span>
      <span>¿Cómo registro una venta y utilizo el código de barras?</span>
      <span class="help-faq-toggle" aria-hidden="true">+</span>
    </summary>
    <div class="help-faq-content">
      <p>El módulo <strong>Facturación / Ventas</strong> permite registrar operaciones y descontar automáticamente el stock vendido.</p>
      <ol>
        <li>Entrá en <strong>Facturación / Ventas</strong> y elegí la opción para registrar una nueva venta.</li>
        <li>Podés agregar productos desde el selector manual o colocar el cursor en <strong>Escanear código de barras</strong> y pasar el lector.</li>
        <li>Si escaneás nuevamente el mismo producto, FME aumenta su cantidad siempre que haya stock disponible.</li>
        <li>Controlá cantidades, medio de pago y total antes de confirmar la venta. Una venta confirmada genera la salida de stock correspondiente.</li>
      </ol>
      <div class="help-tip"><strong>Importante:</strong> si necesitás anular una venta, utilizá la función de cancelación para que el sistema reintegre el stock y conserve el historial.</div>
    </div>
  </details>

  <details class="help-faq-item">
    <summary>
      <span class="help-faq-number">4</span>
      <span>¿Para qué sirven Reportes, Usuarios, Perfiles y Auditoría?</span>
      <span class="help-faq-toggle" aria-hidden="true">+</span>
    </summary>
    <div class="help-faq-content">
      <p>Estos módulos sirven para consultar información y administrar el acceso al sistema. Algunos pueden estar disponibles únicamente para perfiles administrativos.</p>
      <ol>
        <li><strong>Reportes:</strong> consultá resúmenes e informes gráficos para analizar ventas, stock y actividad comercial.</li>
        <li><strong>Usuarios:</strong> administrá las cuentas registradas, sus datos y el perfil asignado.</li>
        <li><strong>Perfiles:</strong> creá o editá perfiles y definí qué módulos puede utilizar cada uno.</li>
        <li><strong>Auditoría:</strong> revisá acciones realizadas dentro del sistema, junto con usuario, fecha, módulo y detalle de la operación.</li>
      </ol>
      <div class="help-tip"><strong>Recordatorio:</strong> si un módulo no aparece en tu menú, normalmente significa que tu perfil no tiene permiso para acceder a él.</div>
    </div>
  </details>
</section>
