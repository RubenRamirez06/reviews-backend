<?php
// ── CONFIGURACIÓN DINÁMICA DE CORS PARA PRODUCCIÓN Y LOCAL ────────────────
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400"); // Cachear preflight por 1 día
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

// Conectamos usando la función adaptada para Railway/Local
$conn = conectar2();

$datos  = file_get_contents('php://input');
$objeto = json_decode($datos);

if ($objeto != null) {
    switch ($objeto->servicio) {

        // ── USUARIOS ─────────────────────────────────────────────
        case "login":
            print json_encode(login($objeto));
            break;

        case "registro":
            print json_encode(registro($objeto));
            break;

        // ── PLATAFORMAS ───────────────────────────────────────────
        case "plataformas":
            print json_encode(listadoPlataformas());
            break;

        // ── CONTENIDOS ────────────────────────────────────────────
        case "contenidos":
            print json_encode(listadoContenidos($objeto->tipo, $objeto->id_plataforma));
            break;

        case "selContenidoID":
            print json_encode(selContenidoID($objeto->id));
            break;

        case "anadeContenido":
            anadeContenido($objeto);
            print json_encode(listadoContenidos($objeto->tipo, $objeto->id_plataforma));
            break;

        case "eliminaContenido":
            eliminaContenido($objeto->id);
            print json_encode(listadoContenidos($objeto->tipo, $objeto->id_plataforma));
            break;

        case "modificaContenido":
            modificaContenido($objeto);
            print json_encode(listadoContenidos($objeto->tipo, $objeto->filtro_plataforma));
            break;

        // ── OPINIONES ─────────────────────────────────────────────
        case "opiniones":
            print json_encode(listadoOpiniones($objeto->id_contenido));
            break;

        case "anadeOpinion":
            anadeOpinion($objeto);
            print json_encode(listadoOpiniones($objeto->id_contenido));
            break;

        case "eliminaOpinion":
            eliminaOpinion($objeto->id);
            print json_encode(listadoOpiniones($objeto->id_contenido));
            break;

        case "modificaOpinion":
            if (modificaOpinion($objeto)) {
                print json_encode(listadoOpiniones($objeto->id_contenido));
            } else {
                print json_encode(array("error" => "No se pudo modificar"));
            }
            break;
    }
}


// ================================================================
//  FUNCIONES DE USUARIO
// ================================================================

function login($objeto) {
    global $conn;
    try {
        $sc  = "SELECT id, nombre, email, avatar FROM usuarios WHERE email = ? AND password = MD5(?)";
        $stm = $conn->prepare($sc);
        $stm->execute(array($objeto->email, $objeto->password));
        $usuario = $stm->fetch(PDO::FETCH_ASSOC);
        if ($usuario) {
            return array("ok" => true, "usuario" => $usuario);
        } else {
            return array("ok" => false, "mensaje" => "Email o contraseña incorrectos");
        }
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

function registro($objeto) {
    global $conn;
    try {
        $sc  = "SELECT id FROM usuarios WHERE email = ?";
        $stm = $conn->prepare($sc);
        $stm->execute(array($objeto->email));
        if ($stm->fetch()) {
            return array("ok" => false, "mensaje" => "El email ya está registrado");
        }

        $sql = "INSERT INTO usuarios(nombre, email, password) VALUES (?, ?, MD5(?))";
        $conn->prepare($sql)->execute(array(
            $objeto->nombre,
            $objeto->email,
            $objeto->password
        ));

        $id      = $conn->lastInsertId();
        $usuario = array("id" => $id, "nombre" => $objeto->nombre, "email" => $objeto->email, "avatar" => null);
        return array("ok" => true, "usuario" => $usuario);

    } catch (Exception $e) {
        die($e->getMessage());
    }
}


// ================================================================
//  FUNCIONES DE PLATAFORMAS
// ================================================================

function listadoPlataformas() {
    global $conn;
    try {
        $sc  = "Select * From plataformas";
        $stm = $conn->prepare($sc);
        $stm->execute();
        return $stm->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die($e->getMessage());
    }
}


// ================================================================
//  FUNCIONES DE CONTENIDOS
// ================================================================

function listadoContenidos($tipo, $id_plataforma) {
    global $conn;
    try {
        if ($id_plataforma == 0) {
            $sc  = "SELECT c.*, p.nombre AS plataforma_nombre,
                           IFNULL(ROUND(AVG(o.valoracion),1), 0) AS media_valoracion
                    FROM contenidos c
                    JOIN plataformas p ON c.id_plataforma = p.id
                    LEFT JOIN opiniones o ON o.id_contenido = c.id
                    WHERE c.tipo = ?
                    GROUP BY c.id
                    ORDER BY c.titulo";
            $stm = $conn->prepare($sc);
            $stm->execute(array($tipo));
        } else {
            $sc  = "SELECT c.*, p.nombre AS plataforma_nombre,
                           IFNULL(ROUND(AVG(o.valoracion),1), 0) AS media_valoracion
                    FROM contenidos c
                    JOIN plataformas p ON c.id_plataforma = p.id
                    LEFT JOIN opiniones o ON o.id_contenido = c.id
                    WHERE c.tipo = ? AND c.id_plataforma = ?
                    GROUP BY c.id
                    ORDER BY c.titulo";
            $stm = $conn->prepare($sc);
            $stm->execute(array($tipo, $id_plataforma));
        }
        return $stm->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

function selContenidoID($id) {
    global $conn;
    try {
        $sc  = "SELECT c.*, p.nombre AS plataforma_nombre,
                       IFNULL(ROUND(AVG(o.valoracion),1), 0) AS media_valoracion
                FROM contenidos c
                JOIN plataformas p ON c.id_plataforma = p.id
                LEFT JOIN opiniones o ON o.id_contenido = c.id
                WHERE c.id = ?
                GROUP BY c.id";
        $stm = $conn->prepare($sc);
        $stm->execute(array($id));
        return $stm->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

function anadeContenido($objeto) {
    global $conn;
    try {
        $sql = "INSERT INTO contenidos(titulo, descripcion, tipo, id_plataforma, foto, anio, id_usuario)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $conn->prepare($sql)->execute(array(
            $objeto->titulo,
            $objeto->descripcion,
            $objeto->tipo,
            $objeto->id_plataforma,
            $objeto->foto,
            $objeto->anio,
            $objeto->id_usuario
        ));
        return true;
    } catch (Exception $e) {
        die($e->getMessage());
        return false;
    }
}

function eliminaContenido($id) {
    global $conn;
    try {
        $sql = "Delete From contenidos Where id = ?";
        $conn->prepare($sql)->execute(array($id));
        return true;
    } catch (Exception $e) {
        die($e->getMessage());
        return false;
    }
}

function modificaContenido($objeto) {
    global $conn;
    try {
        $sql = "UPDATE contenidos SET
                    titulo        = ?,
                    descripcion   = ?,
                    tipo          = ?,
                    id_plataforma = ?,
                    foto          = ?,
                    an20          = ?
                WHERE id = ?";
        $conn->prepare($sql)->execute(array(
            $objeto->titulo,
            $objeto->descripcion,
            $objeto->tipo,
            $objeto->id_plataforma,
            $objeto->foto,
            $objeto->anio,
            $objeto->id
        ));
        return true;
    } catch (Exception $e) {
        die($e->getMessage());
        return false;
    }
}


// ================================================================
//  FUNCIONES DE OPINIONES
// ================================================================

function listadoOpiniones($id_contenido) {
    global $conn;
    try {
        $sc  = "SELECT o.*, u.nombre AS nombre_usuario, u.avatar
                FROM opiniones o
                JOIN usuarios u ON o.id_usuario = u.id
                WHERE o.id_contenido = ?
                ORDER BY o.fecha DESC";
        $stm = $conn->prepare($sc);
        $stm->execute(array($id_contenido));
        return $stm->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

function anadeOpinion($objeto) {
    global $conn;
    try {
        $sql = "INSERT INTO opiniones(id_contenido, id_usuario, valoracion, comentario)
                VALUES (?, ?, ?, ?)";
        $conn->prepare($sql)->execute(array(
            $objeto->id_contenido,
            $objeto->id_usuario,
            $objeto->valoracion,
            $objeto->comentario
        ));
        return true;
    } catch (Exception $e) {
        die($e->getMessage());
        return false;
    }
}

function eliminaOpinion($id) {
    global $conn;
    try {
        $sql = "Delete From opiniones Where id = ?";
        $conn->prepare($sql)->execute(array($id));
        return true;
    } catch (Exception $e) {
        die($e->getMessage());
        return false;
    }
}

function modificaOpinion($objeto) {
    global $conn;
    try {
        $sql = "UPDATE opiniones SET valoracion = ?, comentario = ? WHERE id = ?";
        $conn->prepare($sql)->execute(array(
            $objeto->valoracion,
            $objeto->comentario,
            $objeto->id
        ));
        return true;
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(array("error" => $e->getMessage()));
        exit();
    }
}


// ================================================================
//  FUNCIÓN DE CONEXIÓN INTELIGENTE (LOCAL / RAILWAY)
// ================================================================
function conectar2() {
    try {
        // Si getenv existe lee Railway, si no, usa tus credenciales locales de XAMPP
        $host     = getenv('MYSQLHOST') ?: 'localhost';
        $dbname   = getenv('MYSQLDATABASE') ?: 'reviews_app';
        $usuario  = getenv('MYSQLUSER') ?: 'root';
        $clave    = getenv('MYSQLPASSWORD') ?: '';
        $puerto   = getenv('MYSQLPORT') ?: '3306';

        $opciones = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        $bd = new PDO("mysql:host=$host;port=$puerto;dbname=$dbname", $usuario, $clave, $opciones);
        return $bd;
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(array("error" => "Fallo de conexión: " . $e->getMessage()));
        exit();
    }
}
?>