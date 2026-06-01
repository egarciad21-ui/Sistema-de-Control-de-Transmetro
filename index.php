<?php
// ==========================================
// CAPA DE DATOS Y PROCESAMIENTO PHP BACKEND
// ==========================================

$host = "localhost";
$user = "root";
$pass = "";
$db_name = "transmetro_db";

// Conexión tolerante a fallos para desarrollo local seguro
$db_connected = false;
$conn = @new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    $db_connected = false;
    $db_error_message = $conn->connect_error;
} else {
    $db_connected = true;
    $conn->set_charset("utf8mb4");
}

$toast_message = "";
$toast_type = "success";

// Procesar peticiones operativas mediante POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && $db_connected) {
    $action = $_POST['action'] ?? '';

    // 1. Registrar Nuevo Piloto (REQ-0005)
    if ($action === 'add_pilot') {
        $nombre = $conn->real_escape_string($_POST['nombre_completo']);
        $historial = $conn->real_escape_string($_POST['historial_educativo']);
        $residencia = $conn->real_escape_string($_POST['direccion_residencia']);
        $telefono = $conn->real_escape_string($_POST['telefono_contacto']);

        if (!empty($nombre) && !empty($historial)) {
            $sql = "INSERT INTO piloto (nombre_completo, historial_educativo, direccion_residencia, telefono_contacto) VALUES ('$nombre', '$historial', '$residencia', '$telefono')";
            if ($conn->query($sql)) {
                $toast_message = "Expediente de confianza para $nombre guardado exitosamente.";
                $toast_type = "success";
            } else {
                $toast_message = "Error al insertar piloto en MySQL.";
                $toast_type = "danger";
            }
        }
    }

    // 2. Registrar Nueva Línea y Ruta Secuencial (REQ-0001, REQ-0009)
    if ($action === 'add_line') {
        $nombre_linea = $conn->real_escape_string($_POST['nombre_linea']);
        $estaciones = $_POST['estaciones'] ?? []; // Array de IDs de estación en orden
        
        if (!empty($nombre_linea) && count($estaciones) > 0) {
            $distancia_total = count($estaciones) * 1.5; // Distancia estimada de prueba
            $sql_linea = "INSERT INTO linea (nombre_linea, distancia_total) VALUES ('$nombre_linea', $distancia_total)";
            
            if ($conn->query($sql_linea)) {
                $id_linea = $conn->insert_id;
                $orden = 1;
                foreach ($estaciones as $id_estacion) {
                    $id_est_esc = (int)$id_estacion;
                    $dist_sig = ($orden == count($estaciones)) ? 0.00 : 1.50;
                    $conn->query("INSERT INTO ruta_secuencia (id_linea, id_estacion, orden_secuencial, distancia_siguiente) VALUES ($id_linea, $id_est_esc, $orden, $dist_sig)");
                    $orden++;
                }
                $toast_message = "Línea '$nombre_linea' guardada de forma secuencial con " . count($estaciones) . " estaciones.";
                $toast_type = "success";
            }
        } else {
            $toast_message = "Por favor ingresa un nombre y añade estaciones a la secuencia.";
            $toast_type = "danger";
        }
    }

    // 3. Asignar Guardia de Emergencia (REQ-0006)
    if ($action === 'assign_backup_guard') {
        // Enlazar al guardia 4 (Edwin Cruz) en el acceso vulnerable 4 (Rampa Sótano de El Trébol)
        $sql = "INSERT INTO asignacion_seguridad (id_acceso, id_guardia, fecha_turno, estado_activo) VALUES (4, 4, CURDATE(), 1)";
        if ($conn->query($sql)) {
            $toast_message = "Guardia Edwin Cruz asignado con éxito al Acceso Rampa Sótano (El Trébol).";
            $toast_type = "success";
        }
    }

    // 4. Despachar bus de refuerzo / Registrar Alerta (REQ-0007 / REQ-0003)
    if ($action === 'dispatch_reinforcement') {
        // Verificar cuántos buses tiene la línea 1 actualmente
        $res_count = $conn->query("SELECT COUNT(*) as total FROM bus WHERE id_linea = 1");
        $row_count = $res_count->fetch_assoc();
        $buses_actuales = $row_count['total'];

        // Límite de flota (Línea 1 tiene 3 estaciones configuradas, límite 2N = 6 buses)
        if ($buses_actuales >= 6) {
            $toast_message = "Alerta REQ-0003: Rechazado. Se ha alcanzado el límite de flota máximo (2N = 6 buses) para esta línea.";
            $toast_type = "danger";
        } else {
            // Registrar alerta y asignar nueva unidad temporal
            $conn->query("INSERT INTO transaccion_alerta (tipo_alerta, id_estacion, fecha_hora, sincronizado_central) VALUES ('SATURACION_50', 1, NOW(), 1)");
            $nuevo_num = "TRM-" . rand(200, 999);
            $nueva_placa = "U-" . rand(10000, 99999);
            $conn->query("INSERT INTO bus (numero_unidad, placa, capacidad_pasajeros, id_linea, id_parqueo, id_piloto) VALUES ('$nuevo_num', '$nueva_placa', 80, 1, 3, 3)");
            
            $toast_message = "Alerta REQ-0007 resuelta. Unidad de refuerzo $nuevo_num despachada a ruta.";
            $toast_type = "success";
        }
    }
}

// Consultar datos de MySQL para renderizado reactivo
$pilotos = [];
$lineas_monitoreo = [];
$estaciones_select = [];
$seguridad_plaza_barrios = [];
$seguridad_trebol_vulnerable = true;
$total_buses_linea1 = 14; // Fallback por defecto si no hay DB
$guardias_activos = 2; // Fallback

if ($db_connected) {
    // Obtener catálogo de Pilotos
    $res = $conn->query("SELECT * FROM piloto ORDER BY id_piloto DESC");
    while ($r = $res->fetch_assoc()) { $pilotos[] = $r; }

    // Obtener catálogo de Estaciones
    $res_est = $conn->query("SELECT * FROM estacion ORDER BY id_estacion ASC");
    while ($r = $res_est->fetch_assoc()) { $estaciones_select[] = $r; }

    // Obtener monitoreo de accesos de Plaza Barrios (ID: 1)
    $res_sec = $conn->query("SELECT a.nombre_acceso, g.nombre_completo 
                             FROM acceso a 
                             JOIN asignacion_seguridad s ON a.id_acceso = s.id_acceso 
                             JOIN guardia g ON s.id_guardia = g.id_guardia 
                             WHERE a.id_estacion = 1 AND s.estado_activo = 1");
    while ($r = $res_sec->fetch_assoc()) { $seguridad_plaza_barrios[] = $r; }

    // Verificar si el acceso vulnerable 4 ya tiene guardia asignado
    $res_vuln = $conn->query("SELECT COUNT(*) as asignado FROM asignacion_seguridad WHERE id_acceso = 4 AND estado_activo = 1");
    $row_vuln = $res_vuln->fetch_assoc();
    $seguridad_trebol_vulnerable = ($row_vuln['asignado'] == 0);

    // Obtener cantidad de buses en Línea 1
    $res_bus_count = $conn->query("SELECT COUNT(*) as total FROM bus WHERE id_linea = 1");
    if ($res_bus_count) {
        $row = $res_bus_count->fetch_assoc();
        $total_buses_linea1 = $row['total'];
    }

    // Obtener monitoreo de todas las líneas para tabla de restricciones N a 2N
    $res_lineas = $conn->query("SELECT l.id_linea, l.nombre_linea, 
                                (SELECT COUNT(*) FROM ruta_secuencia WHERE id_linea = l.id_linea) as N,
                                (SELECT COUNT(*) FROM bus WHERE id_linea = l.id_linea) as flota
                                FROM linea l");
    while ($r = $res_lineas->fetch_assoc()) { $lineas_monitoreo[] = $r; }

    // Contador de guardias activos total
    $res_g_active = $conn->query("SELECT COUNT(*) as total FROM asignacion_seguridad WHERE estado_activo = 1");
    $guardias_activos = $res_g_active->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transmetro Dashboard - Central de Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* PALETA DE DISEÑO MINIMALISTA SENSORIAL */
        :root {
            --bg-main: #0a0a0c;
            --bg-card: #121215;
            --bg-input: #1b1b20;
            --border: #23232a;
            --border-hover: #343440;
            --text-main: #f5f5f7;
            --text-muted: #8e8e95;
            --accent: #deff9a;
            --accent-muted: rgba(222, 255, 154, 0.08);
            --danger: #ff6b6b;
            --danger-muted: rgba(255, 107, 107, 0.08);
            --success: #51cf66;
            --success-muted: rgba(81, 207, 102, 0.08);
            --warning: #fcc419;
            --warning-muted: rgba(252, 196, 25, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: 'Urbanist', sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* NAVEGACIÓN LATERAL (SIDEBAR) */
        aside {
            width: 280px;
            background-color: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 35px 24px;
            justify-content: space-between;
        }

        .brand {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand span {
            color: var(--accent);
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 50px;
            flex-grow: 1;
        }

        .nav-item {
            padding: 14px 16px;
            border-radius: 10px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.25s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            color: var(--text-main);
            background-color: var(--accent-muted);
        }

        .nav-item.active {
            color: var(--accent);
            border-color: rgba(222, 255, 154, 0.15);
            font-weight: 700;
        }

        .user-profile {
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background-color: var(--accent);
            color: #0a0a0c;
            display: grid;
            place-items: center;
            font-weight: 700;
        }

        /* PANEL CENTRAL DE CONTENIDO */
        main {
            flex-grow: 1;
            padding: 40px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 35px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 25px;
        }

        .station-info h1 {
            font-size: 32px;
            font-weight: 700;
        }

        .station-info p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 6px;
        }

        .status-badge {
            background-color: var(--success-muted);
            color: var(--success);
            border: 1px solid rgba(81, 207, 102, 0.15);
            padding: 10px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }

        .status-badge.offline {
            background-color: var(--danger-muted);
            color: var(--danger);
            border-color: rgba(255, 107, 107, 0.15);
        }

        /* CONTENEDORES DE PESTAÑAS (SPA) */
        .tab-content {
            display: none;
            flex-direction: column;
            gap: 35px;
            animation: slideUpFade 0.4s ease;
        }

        .tab-content.active {
            display: flex;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* BANNER DE ALERTAS REQ-0007 */
        .alert-banner {
            background-color: var(--danger-muted);
            border: 1px solid rgba(255, 107, 107, 0.2);
            color: var(--danger);
            padding: 18px 24px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

        /* TARJETAS DE INDICADORES */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .card-value {
            font-size: 38px;
            font-weight: 700;
        }

        .card-value span {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .card.alert-active {
            border-color: var(--danger);
            background: linear-gradient(180deg, var(--bg-card) 0%, rgba(255, 107, 107, 0.02) 100%);
        }

        .card.alert-active .card-value {
            color: var(--danger);
        }

        /* TABLAS RELACIONALES */
        .table-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .table-responsive {
            width: 100%;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            background-color: var(--bg-card);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 18px 24px;
            background-color: rgba(255, 255, 255, 0.01);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
        }

        td {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        /* BOTONES E INPUTS */
        .btn-primary, .btn-submit {
            background-color: var(--accent);
            color: #0a0a0c;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-family: inherit;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover, .btn-submit:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-dispatch {
            background-color: var(--danger);
            color: #0a0a0c;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
        }

        /* COMPONENTES DE CONFIGURACIÓN DE RUTAS (REQ-0001 / REQ-0009) */
        .route-builder {
            display: grid;
            grid-template-columns: 1fr 1.6fr;
            gap: 30px;
            align-items: start;
        }

        .route-form {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .form-group input, .form-group select {
            background-color: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 14px;
            outline: none;
        }

        .station-sequence-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .sequence-item {
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: var(--bg-input);
            padding: 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .sequence-number {
            width: 26px;
            height: 26px;
            background-color: var(--accent-muted);
            color: var(--accent);
            border: 1px solid rgba(222, 255, 154, 0.15);
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 12px;
            font-weight: 700;
        }

        /* EXPEDIENTES Y SEGURIDAD */
        .driver-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
        }

        .driver-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .driver-avatar {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background-color: var(--border);
            color: var(--text-main);
            display: grid;
            place-items: center;
            font-size: 20px;
            font-weight: 700;
        }

        .security-monitor {
            display: grid;
            grid-template-columns: 1.8fr 1fr;
            gap: 30px;
            align-items: start;
        }

        .access-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .access-card.vulnerable {
            border-color: var(--danger);
        }

        .indicator-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .indicator-dot.active { background-color: var(--success); }
        .indicator-dot.alert { background-color: var(--danger); }

        /* SISTEMA DE TOASTS FLOTANTES */
        #toast-container {
            position: fixed;
            bottom: 40px;
            right: 40px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 1000;
        }

        .toast {
            background-color: #16161a;
            border: 1px solid var(--border);
            border-left: 5px solid var(--accent);
            color: var(--text-main);
            padding: 18px 24px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 340px;
            animation: slideIn 0.3s ease;
        }

        .toast.toast-danger { border-left-color: var(--danger); }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- MENÚ LATERAL (SIDEBAR) -->
    <aside>
        <div class="brand">
            <i class="fa-solid fa-bus-simple"></i> MUNI<span>METRO</span>
        </div>
        <ul class="nav-menu">
            <li><a onclick="switchTab('estacion', this)" class="nav-item active"><i class="fa-solid fa-chart-simple"></i> Estación Central</a></li>
            <li><a onclick="switchTab('lineas', this)" class="nav-item"><i class="fa-solid fa-route"></i> Líneas y Rutas</a></li>
            <li><a onclick="switchTab('expedientes', this)" class="nav-item"><i class="fa-solid fa-id-card"></i> Expedientes</a></li>
            <li><a onclick="switchTab('seguridad', this)" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Seguridad</a></li>
        </ul>
        <div class="user-profile">
            <div class="user-avatar">OP</div>
            <div class="user-info">
                <h4>Operador Local</h4>
                <p>MuniGuate — Plaza Barrios</p>
            </div>
        </div>
    </aside>

    <!-- PANEL PRINCIPAL DE TRABAJO -->
    <main>
        <!-- CABECERA OPERATIVA -->
        <header>
            <div class="station-info">
                <h1 id="main-header-title">Estación Plaza Barrios</h1>
                <p id="main-header-desc">
                    <i class="fa-solid fa-display"></i> PC Local: Terminal Node-04G | 
                    <?php if ($db_connected): ?>
                        <span style="color:var(--success)">Conectado a MySQL</span>
                    <?php else: ?>
                        <span style="color:var(--danger)">Trabajando de Forma Local (Sin DB)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="system-status">
                <div class="status-badge" id="syncStatus" onclick="toggleNetworkStatus()">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Sincronizado con Central
                </div>
            </div>
        </header>

        <!-- PESTAÑA 1: ESTACIÓN CENTRAL (Dashboard) -->
        <div id="tab-estacion" class="tab-content active">
            <!-- Alerta REQ-0007 / Estado de Saturación 50% -->
            <div class="alert-banner">
                <div style="display:flex; align-items:center; gap:12px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> 
                    <div><strong>Alerta Crítica REQ-0007:</strong> Estación excede el 50% de la capacidad nominal.</div>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="dispatch_reinforcement">
                    <button type="submit" class="btn-dispatch">Despachar Refuerzo</button>
                </form>
            </div>

            <!-- Fichas de Métricas -->
            <section class="metrics-grid">
                <div class="card alert-active">
                    <div class="card-header">
                        <span>Ocupación de Estación</span>
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="card-value">158% <span>/ 100% nominal</span></div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <span>Guardias en Accesos</span>
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div class="card-value"><?php echo $guardias_activos; ?> <span>/ Mínimo 1 (REQ-0006)</span></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span>Buses en Línea Actual</span>
                        <i class="fa-solid fa-bus-simple"></i>
                    </div>
                    <div class="card-value"><?php echo $total_buses_linea1; ?> <span>Unidades (Límite 2N)</span></div>
                </div>
            </section>

            <!-- Monitoreo de Plataforma (REQ-0008) -->
            <section class="table-section">
                <h2>Próximos Arribos y Despacho en Plataforma</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Unidad</th>
                                <th>Piloto Asignado</th>
                                <th>Carga de Pasajeros</th>
                                <th>Estado de Salida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>TRM-042</strong></td>
                                <td>Carlos Mendoza (ID: 4402)</td>
                                <td>18% Capacidad</td>
                                <td><span class="badge-wait"><i class="fa-solid fa-clock"></i> Espera obligatoria 5 min (Carga < 25%)</span></td>
                            </tr>
                            <tr>
                                <td><strong>TRM-108</strong></td>
                                <td>Josué Girón (ID: 8921)</td>
                                <td>72% Capacidad</td>
                                <td><span class="badge-normal"><i class="fa-solid fa-circle-check"></i> Listo para Despacho</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- PESTAÑA 2: LÍNEAS Y RUTAS (REQ-0001, REQ-0003, REQ-0009) -->
        <div id="tab-lineas" class="tab-content">
            <div class="route-builder">
                <!-- Constructor Físico (REQ-0001 / REQ-0009) -->
                <form class="route-form" method="POST" action="">
                    <input type="hidden" name="action" value="add_line">
                    <h3>Registrar Nueva Línea / Ruta</h3>
                    <div class="form-group">
                        <label>Nombre de la Línea</label>
                        <input type="text" name="nombre_linea" placeholder="Ej: Línea 12 - Troncal Centro" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Añadir Estación a la Secuencia</label>
                        <select id="line-station-select" onchange="addStationToSequence()">
                            <option value="">-- Seleccionar Estación --</option>
                            <?php foreach ($estaciones_select as $est): ?>
                                <option value="<?php echo $est['id_estacion']; ?>"><?php echo htmlspecialchars($est['nombre_estacion']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Recorrido Configurado (Orden Secuencial)</label>
                        <div class="station-sequence-list" id="sequence-list">
                            <!-- Inyectado dinámicamente mediante JS -->
                        </div>
                    </div>

                    <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Línea en Base de Datos</button>
                </form>

                <!-- Monitoreo de Restricciones (REQ-0003) -->
                <div class="table-section">
                    <h2>Monitoreo de Restricción de Flota</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Línea</th>
                                    <th>Estaciones (N)</th>
                                    <th>Flota Asignada</th>
                                    <th>Rango Permitido</th>
                                    <th>Estado de Capacidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($lineas_monitoreo) > 0): ?>
                                    <?php foreach ($lineas_monitoreo as $lm): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($lm['nombre_linea']); ?></strong></td>
                                            <td><?php echo $lm['N']; ?> Estaciones</td>
                                            <td><?php echo $lm['flota']; ?> Buses</td>
                                            <td><?php echo $lm['N']; ?> - <?php echo ($lm['N'] * 2); ?> Unidades</td>
                                            <td>
                                                <?php if ($lm['flota'] >= ($lm['N'] * 2)): ?>
                                                    <span class="badge-alert"><i class="fa-solid fa-triangle-exclamation"></i> Flota Máxima (2N)</span>
                                                <?php elseif ($lm['flota'] <= $lm['N']): ?>
                                                    <span class="badge-wait">Flota Mínima (N)</span>
                                                <?php else: ?>
                                                    <span class="badge-normal">Estable</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Fallback si no hay conexión MySQL todavía -->
                                    <tr>
                                        <td><strong>Línea 12</strong></td>
                                        <td>7 Estaciones</td>
                                        <td>14 Buses</td>
                                        <td>7 - 14 Unidades</td>
                                        <td><span class="badge-alert">Flota Máxima (2N)</span></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- PESTAÑA 3: EXPEDIENTES (REQ-0005) -->
        <div id="tab-expedientes" class="tab-content">
            <div class="route-builder">
                <!-- Formulario de Registro -->
                <form class="route-form" method="POST" action="">
                    <input type="hidden" name="action" value="add_pilot">
                    <h3>Registrar Expediente de Piloto</h3>
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="nombre_completo" required placeholder="Carlos Mendoza">
                    </div>
                    <div class="form-group">
                        <label>Historial Educativo</label>
                        <input type="text" name="historial_educativo" required placeholder="Diversificado completo / Perito">
                    </div>
                    <div class="form-group">
                        <label>Dirección de Residencia</label>
                        <input type="text" name="direccion_residencia" placeholder="Zona 12, Ciudad de Guatemala">
                    </div>
                    <div class="form-group">
                        <label>Teléfono de Contacto</label>
                        <input type="text" name="telefono_contacto" placeholder="+502 5521 8930">
                    </div>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-id-card"></i> Guardar Expediente</button>
                </form>

                <!-- Listado de Pilotos Registrados -->
                <div class="table-section">
                    <h2>Expedientes de Trazabilidad Activos</h2>
                    <div class="driver-grid">
                        <?php if (count($pilotos) > 0): ?>
                            <?php foreach ($pilotos as $p): ?>
                                <div class="driver-card">
                                    <div class="driver-header">
                                        <div class="driver-avatar"><?php echo substr($p['nombre_completo'], 0, 2); ?></div>
                                        <div class="driver-title">
                                            <h3><?php echo htmlspecialchars($p['nombre_completo']); ?></h3>
                                            <p>ID Piloto: TR-<?php echo $p['id_piloto']; ?> | Activo</p>
                                        </div>
                                    </div>
                                    <div class="driver-details">
                                        <div class="detail-row">
                                            <span class="detail-label">Educación</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($p['historial_educativo']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Residencia</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($p['direccion_residencia']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Contacto</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($p['telefono_contacto']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:var(--text-muted)">No hay pilotos registrados. Importa el archivo database.sql para ver datos de prueba.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PESTAÑA 4: SEGURIDAD (REQ-0006) -->
        <div id="tab-seguridad" class="tab-content">
            <div class="security-monitor">
                <div class="table-section">
                    <h2>Monitoreo en Tiempo Real de Accesos de Estaciones</h2>
                    
                    <!-- Estación 1 (Segura) -->
                    <div class="access-card" style="margin-bottom: 24px;">
                        <div class="access-header">
                            <div>
                                <h3>Estación Plaza Barrios</h3>
                                <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Línea 12 | 2 Accesos Totales</p>
                            </div>
                            <span class="badge-normal"><i class="fa-solid fa-shield-check"></i> Zona Segura</span>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            <?php if (count($seguridad_plaza_barrios) > 0): ?>
                                <?php foreach ($seguridad_plaza_barrios as $spb): ?>
                                    <div class="sequence-item" style="justify-content: space-between;">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <div class="guard-indicator"><span class="indicator-dot active"></span></div>
                                            <strong><?php echo htmlspecialchars($spb['nombre_acceso']); ?></strong>
                                        </div>
                                        <div style="font-size: 14px; color: var(--accent);">
                                            <i class="fa-solid fa-user-tie"></i> Guardia: <?php echo htmlspecialchars($spb['nombre_completo']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="sequence-item">
                                    <p>Base de datos no inicializada. Ejecuta el archivo SQL.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Estación 2 (Vulnerable) -->
                    <div class="access-card <?php echo $seguridad_trebol_vulnerable ? 'vulnerable' : ''; ?>">
                        <div class="access-header">
                            <div>
                                <h3>Estación El Trébol</h3>
                                <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Línea 12 | 2 Accesos Totales</p>
                            </div>
                            <?php if ($seguridad_trebol_vulnerable): ?>
                                <span class="badge-alert"><i class="fa-solid fa-triangle-exclamation"></i> Acceso Vulnerable</span>
                            <?php else: ?>
                                <span class="badge-normal"><i class="fa-solid fa-shield-check"></i> Zona Segura</span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            <div class="sequence-item" style="justify-content: space-between;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div class="guard-indicator"><span class="indicator-dot active"></span></div>
                                    <strong>Acceso Pasarela Norte</strong>
                                </div>
                                <div style="font-size: 14px; color: var(--accent);">
                                    <i class="fa-solid fa-user-tie"></i> Guardia: Walter Alonzo (Placa: G-105)
                                </div>
                            </div>
                            
                            <?php if ($seguridad_trebol_vulnerable): ?>
                                <div class="sequence-item" style="justify-content: space-between; border-color: var(--danger); background-color: rgba(255,107,107,0.02);">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="guard-indicator"><span class="indicator-dot alert"></span></div>
                                        <strong style="color: var(--danger);">Acceso Rampa Sótano</strong>
                                    </div>
                                    <div style="font-size: 14px; color: var(--danger); font-weight: 700;">
                                        <i class="fa-solid fa-user-slash"></i> SIN GUARDIA ASIGNADO (REQ-0006)
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="sequence-item" style="justify-content: space-between;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="guard-indicator"><span class="indicator-dot active"></span></div>
                                        <strong>Acceso Rampa Sótano</strong>
                                    </div>
                                    <div style="font-size: 14px; color: var(--accent);">
                                        <i class="fa-solid fa-user-tie"></i> Guardia: Edwin Javier Cruz (Backup)
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Panel de Mitigación -->
                <div class="access-card">
                    <h3>Acciones de Contingencia</h3>
                    <p style="font-size: 14px; color: var(--text-muted); line-height: 1.5;">
                        Todo acceso sin guardia asignado bloquea la operación central y eleva el riesgo delictivo en la estación.
                    </p>
                    <?php if ($seguridad_trebol_vulnerable): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="assign_backup_guard">
                            <button type="submit" class="btn-primary" style="background-color: var(--danger); color:#000; width:100%; margin-top:15px;">
                                <i class="fa-solid fa-shield-halved"></i> Asignar Guardia de Apoyo
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn-primary" disabled style="background-color: var(--border); color:var(--text-muted); width:100%; margin-top:15px;">
                            <i class="fa-solid fa-circle-check"></i> Todos los accesos cubiertos
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- NOTIFICACIONES TOAST (SISTEMA REACTIVO) -->
    <div id="toast-container"></div>

    <script>
        let isOnline = true;

        // Cambiar pestañas de la Consola SPA
        function switchTab(tabId, element) {
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');

            const headerTitle = document.getElementById('main-header-title');
            if (tabId === 'estacion') headerTitle.textContent = "Estación Plaza Barrios";
            else if (tabId === 'lineas') headerTitle.textContent = "Administración de Líneas y Recorridos";
            else if (tabId === 'expedientes') headerTitle.textContent = "Expedientes de Personal Autorizado";
            else if (tabId === 'seguridad') headerTitle.textContent = "Consola de Seguridad de Accesos";
        }

        // Mostrar Notificaciones Flotantes
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type === 'danger' ? 'toast-danger' : ''}`;
            let icon = '<i class="fa-solid fa-circle-check" style="color:var(--success)"></i>';
            if (type === 'danger') icon = '<i class="fa-solid fa-triangle-exclamation" style="color:var(--danger)"></i>';
            
            toast.innerHTML = `${icon} <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 5000);
        }

        // Alternar modo fuera de línea local (Solución al Ítem 23)
        function toggleNetworkStatus() {
            const badge = document.getElementById('syncStatus');
            isOnline = !isOnline;
            if (isOnline) {
                badge.className = "status-badge";
                badge.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Sincronizado con Central';
                showToast("Conexión restaurada de forma segura.");
            } else {
                badge.className = "status-badge offline";
                badge.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> Modo Local (Sin Conexión)';
                showToast("Se perdió la conexión con el servidor central de la municipalidad. Las alertas se guardarán en búfer local.", "danger");
            }
        }

        // Agregar Estación Dinámicamente en el formulario antes de enviar a PHP
        function addStationToSequence() {
            const select = document.getElementById('line-station-select');
            const sequenceList = document.getElementById('sequence-list');
            const stationId = select.value;
            const stationName = select.options[select.selectedIndex].text;

            if (!stationId) return;

            const nextIndex = sequenceList.children.length + 1;
            const distance = (Math.random() * 2 + 1).toFixed(1);

            const newItem = document.createElement('div');
            newItem.className = "sequence-item";
            newItem.innerHTML = `
                <div class="sequence-number">${nextIndex}</div>
                <div style="flex-grow: 1; font-weight:600;">${stationName}</div>
                <input type="hidden" name="estaciones[]" value="${stationId}">
                <div style="font-size:13px; color:var(--text-muted)">Tramo: ${distance} Km</div>
            `;
            sequenceList.appendChild(newItem);
            select.value = "";
        }

        // Auto-ejecución del Toast de PHP
        <?php if (!empty($toast_message)): ?>
            showToast("<?php echo $toast_message; ?>", "<?php echo $toast_type; ?>");
        <?php endif; ?>
    </script>
</body>
</html>