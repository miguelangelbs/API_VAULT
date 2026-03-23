<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AdministradoresModelo;
use App\Models\DesarrolladoraModelo;
use App\Services\ApiKeyValidator;
use App\Models\VideojuegoModelo;
use CodeIgniter\I18n\Time;
use App\Models\GeneroModelo;
use App\Models\PlataformaModelo;
use App\Models\PublisherModelo;
use App\Models\TiendaModelo;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Cloudinary\Cloudinary;

//Controlador que gestiona consultas directas a la base de datos
class DataController extends BaseController
{

    protected $request;
    protected $response;
    public $apiKeyValidator;

    /*CONSTRUCTOR*/
    public function __construct()
    {
        $this->apiKeyValidator = new ApiKeyValidator();
        $this->VideojuegoModelo = new VideojuegoModelo();
        $this->AdministradoresModelo = new AdministradoresModelo();
        $this->GeneroModelo = new GeneroModelo();
        $this->DesarrolladoraModelo = new DesarrolladoraModelo();
        $this->PlataformaModelo = new PlataformaModelo();
        $this->PublisherModelo = new PublisherModelo();
        $this->TiendaModelo = new TiendaModelo();
    }

    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Retorna todos los videojuegos registrados en la base de datos.
     *
     * Valida la API key y, si es válida, consulta y retorna todos los videojuegos almacenados.
     * Si la consulta no retorna resultados, se informa que no se encontraron videojuegos.
     * Se utilizan bloques try-catch para controlar errores en la recuperación de datos.
     */
    public function recibirJuegos()
    {
        //Valida la API key para autorizar la consulta
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);

        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene todos los videojuegos de la base de datos
            $juegos = $this->VideojuegoModelo->findAll();

            //Prepara la respuesta en función de si se encontraron registros o no
            if (empty($juegos)) {
                $data = ['mensaje' => 'No se encontraron videojuegos'];
            } else {
                $data = ['juegos' => $juegos];
            }

            return $this->response->setJSON($data)->setStatusCode(200);
        } catch (\Exception $e) {
            log_message('error', 'Error al obtener juegos: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar los datos'
            ])->setStatusCode(500);
        }
    }

    /**
     * Retorna los datos de un videojuego específico por su ID.
     *
     * Valida la API key y, en caso de éxito, consulta el juego en la base de datos utilizando
     * el modelo correspondiente. Si no se encuentra el juego, retorna un error 404. Ante cualquier
     * excepción, se retorna un error 500.
     */
    public function recibirDatosJuego($id)
    {   
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {  
            //Busca el videojuego por su ID
            $juego = $this->VideojuegoModelo->find($id);

            //Retorna un error 404 si el videojuego no existe
            if (!$juego) {
                return $this->response->setJSON([
                    'error' => 'No se encontró videojuego con ese ID'
                ])->setStatusCode(404);
            }

            //Retorna los datos del videojuego en una respuesta JSON
            return $this->response->setJSON(['juego' => $juego])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna una respuesta 500 en caso de excepción
            log_message('error', 'Error al obtener el juego con ID ' . $id . ': ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar los datos del juego'
            ])->setStatusCode(500);
        }
    }

    /**
     * Maneja el inicio de sesión de un administrador.
     *
     * Valida la API key y procesa la solicitud de inicio de sesión recibiendo datos en formato JSON.
     * Verifica que el administrador exista y que la contraseña sea correcta. Si la autenticación es
     * exitosa, actualiza la fecha del último inicio de sesión y retorna los datos relevantes del administrador.
     */
    public function inicioSesion()
    {   
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {  
            //Obtiene los datos de la solicitud en formato JSON
            $data = $this->request->getJSON(true) ?? [];

            //Extrae el nombre y la contraseña, eliminando espacios innecesarios
            $nombre = trim($data['nombre'] ?? '');
            $password = trim($data['password'] ?? '');

            //Busca el administrador por nombre en la base de datos
            $administrador = $this->AdministradoresModelo->where('nombre', $nombre)->first();
            if (!$administrador) {
                return $this->response->setJSON([
                    'error' => 'Usuario no encontrado'
                ])->setStatusCode(404);
            }

            //Verifica que la contraseña ingresada coincida con la almacenada
            if (!password_verify($password, $administrador['password'])) {
                return $this->response->setJSON([
                    'error' => 'Contraseña incorrecta'
                ])->setStatusCode(401);
            }

            //Actualiza la fecha del último inicio de sesión
            $fechaActual = Time::now('Europe/Madrid')->toDateTimeString();
            $this->AdministradoresModelo->update($administrador['id'], [
                'fecha_ultimo_login' => $fechaActual
            ]);

            //Retorna una respuesta exitosa con los datos del administrador
            return $this->response->setJSON([
                'mensaje' => 'Inicio de sesión exitoso',
                'administrador' => [
                    'id' => $administrador['id'],
                    'nombre' => $administrador['nombre'],
                    'fecha_creacion' => $administrador['fecha_creacion'],
                    'fecha_ultimo_login' => $fechaActual,
                ]
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje genérico de fallo en el inicio de sesión
            log_message('error', 'Error en inicioSesion: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error en el inicio de sesión'
            ])->setStatusCode(500);
        }
    }

    /**
     * Retorna la lista de géneros desde la base de datos.
     *
     * Valida la API key y, en caso de éxito, consulta el modelo de géneros para obtener todos los registros.
     * Si no se encuentran géneros, retorna un error 404; en caso de excepción, registra el error y responde con un error 500.
     */
    public function recibirGeneros()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene todos los géneros almacenados en la base de datos
            $generos = $this->GeneroModelo->findAll();

            //Verifica si se encontraron géneros y retorna el resultado correspondiente
            if (empty($generos)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron géneros'
                ])->setStatusCode(404);
            }

            return $this->response->setJSON([
                'generos' => $generos
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra y retorna un error en caso de excepción
            log_message('error', 'Error al obtener géneros: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar los géneros'
            ])->setStatusCode(500);
        }
    }

    /**
     * Devuelve la lista de plataformas disponibles.
     *
     * Valida la API key y consulta el modelo de plataformas. Si no se encuentran registros,
     * retorna un error 404; de lo contrario, retorna la lista de plataformas en formato JSON.
     * En caso de cualquier excepción, se registra el error y se devuelve un error 500.
     */
    public function recibirPlataformas()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene todas las plataformas desde la base de datos
            $plataformas = $this->PlataformaModelo->findAll();

            //Verifica si se encontraron registros
            if (empty($plataformas)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron plataformas'
                ])->setStatusCode(404);
            }

            //Retorna la lista de plataformas en una respuesta JSON
            return $this->response->setJSON([
                'plataformas' => $plataformas
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            log_message('error', 'Error al obtener plataformas: ' . $e->getMessage());

            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar las plataformas'
            ])->setStatusCode(500);
        }
    }

    /**
     * Retorna todas las tiendas registradas en la base de datos.
     *
     * Valida la API key y, de ser exitosa, consulta el modelo de tiendas para obtener todos los registros.
     * Si no se encuentran tiendas, retorna un error 404; ante cualquier excepción, retorna un error 500.
     */
    public function recibirTiendas()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Recupera todas las tiendas usando el modelo correspondiente
            $tiendas = $this->TiendaModelo->findAll();

            //Si no se encontraron registros, retorna un error 404
            if (empty($tiendas)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron tiendas'
                ])->setStatusCode(404);
            }

            //Retorna la lista de tiendas en una respuesta JSON exitosa
            return $this->response->setJSON([
                'tiendas' => $tiendas
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje de error 500 en caso de excepción
            log_message('error', 'Error al obtener tiendas: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar las tiendas'
            ])->setStatusCode(500);
        }
    }

    /**
     * Obtiene la lista completa de desarrolladoras.
     *
     * Valida la API key y consulta el modelo de desarrolladoras. Si no se encuentran registros,
     * retorna un error 404; si ocurre alguna excepción, se registra el error y se responde con un error 500.
     */
    public function recibirDesarrolladoras()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene todas las desarrolladoras de la base de datos
            $desarrolladoras = $this->DesarrolladoraModelo->findAll();

            //Si no se encuentran registros, retorna un error 404
            if (empty($desarrolladoras)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron desarrolladoras'
                ])->setStatusCode(404);
            }

            //Retorna la lista de desarrolladoras en una respuesta JSON
            return $this->response->setJSON([
                'desarrolladoras' => $desarrolladoras
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje de error 500
            log_message('error', 'Error al obtener desarrolladoras: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar las desarrolladoras'
            ])->setStatusCode(500);
        }
    }

    /**
     * Recupera la lista de publishers disponibles.
     *
     * Valida la API key y consulta el modelo de publishers. Si no se encuentran registros,
     * retorna un error 404; en caso de excepción, se registra el error y se responde con un error 500.
     */
    public function recibirPublishers()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene todos los publishers desde la base de datos
            $publishers = $this->PublisherModelo->findAll();

            //Si no se encuentran registros, retorna un error 404
            if (empty($publishers)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron publishers'
                ])->setStatusCode(404);
            }

            //Retorna la lista de publishers en una respuesta JSON exitosa
            return $this->response->setJSON([
                'publishers' => $publishers
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje de error 500 en caso de excepción
            log_message('error', 'Error al obtener publishers: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar los publishers'
            ])->setStatusCode(500);
        }
    }

    /**
     * Retorna videojuegos filtrados según la categoría y el nombre asociado a esa categoría.
     *
     * Valida la API key y comprueba que la categoría solicitada se encuentre entre las permitidas.
     * Luego, ejecuta una consulta SQL que filtra los videojuegos cuyo campo JSON (de la categoría
     * especificada) contenga el nombre buscado, sin distinción de mayúsculas o minúsculas.
     * Retorna un error 404 si no se encuentran registros, o un mensaje 400 si la categoría no es válida.
     */
    public function recibirJuegosFiltrados($categoria, $nombre)
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        //Define las columnas permitidas para filtrar y valida la categoría recibida
        $columnasPermitidas = ['generos', 'tiendas', 'publishers', 'desarrolladoras', 'plataformas_principales'];
        if (!in_array($categoria, $columnasPermitidas)) {
            return $this->response->setJSON(['error' => 'Categoría no válida'])
                ->setStatusCode(400);
        }

        try {
            //Conecta a la base de datos
            $db = \Config\Database::connect();

            // Prepara la consulta para filtrar videojuegos que contengan en el array JSON del campo "$categoria"
            // algún objeto cuyo atributo 'nombre' coincida, sin distinción de mayúsculas/minúsculas, con el valor buscado.
            $query = "
                SELECT * 
                FROM vault.videojuegos
                WHERE EXISTS (
                    SELECT 1
                    FROM jsonb_array_elements($categoria) AS item
                    WHERE LOWER(item->>'nombre') LIKE LOWER(?)
                )
                ORDER BY nombre ASC
            ";

            //Ejecuta la consulta utilizando comodines para buscar coincidencias parciales
            $result = $db->query($query, ['%' . $nombre . '%'])->getResult();
            
            //Si no se encuentran resultados, retorna un error 404
            if (empty($result)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron videojuegos con esa categoría'
                ])->setStatusCode(404);
            }

            //Retorna los videojuegos filtrados en una respuesta JSON exitosa
            return $this->response->setJSON([
                'juegosFiltrados' => $result
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje de error 500 en caso de excepción
            log_message('error', 'Error al obtener juegos filtrados: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar los juegos filtrados'
            ])->setStatusCode(500);
        }
    }

    /**
     * Elimina un videojuego de la base de datos y su imagen en Cloudinary (si aplica).
     *
     * Valida la API key, extrae el ID del juego desde el JSON de la solicitud y verifica su existencia.
     * Si el juego tiene una imagen alojada en Cloudinary, se elimina mediante la API de Cloudinary.
     * Finalmente, se borra el registro del juego en la base de datos.
     */
    public function eliminarJuego()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        //Obtiene los datos JSON y extrae el ID del juego
        $json = $this->request->getJSON();
        $id = $json->id ?? null;

        if (!$id) {
            return $this->response->setJSON(['error' => 'ID del juego no proporcionado'])
                ->setStatusCode(400);
        }
        
        //Busca el videojuego por su ID
        $juego = $this->VideojuegoModelo->find($id);
        if (!$juego) {
            return $this->response->setJSON(['error' => 'Juego no encontrado'])
                ->setStatusCode(404);
        }

        try {
            //Si la imagen se aloja en Cloudinary, procede a eliminarla
            if (strpos($juego['imagen'], 'res.cloudinary.com') !== false) {
                $cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => getenv('CLOUDINARY_API_KEY'),
                        'api_secret' => getenv('CLOUDINARY_API_SECRET'),
                    ],
                ]);

                //Extrae el public ID de la URL y elimina la imagen en Cloudinary
                $publicId = $this->extraerPublicIdDesdeUrl($juego['imagen']);
                if ($publicId) {
                    $cloudinary->uploadApi()->destroy($publicId);
                }
            }
            //Elimina el registro del videojuego de la base de datos
            $this->VideojuegoModelo->delete($id);

            return $this->response->setJSON([
                'success' => true,
                'mensaje' => 'Juego eliminado correctamente'
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            log_message('error', 'Error al eliminar juego: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al eliminar el juego'
            ])->setStatusCode(500);
        }
    }

    /**
     * Recupera los datos necesarios para poblar el formulario de creación/edición de videojuegos.
     *
     * Valida la API key y consulta los modelos de tiendas, plataformas, géneros, desarrolladoras
     * y publishers para obtener los registros almacenados. En caso de excepción, se registra el error
     * y se retorna un mensaje indicando la falla.
     */
    public function obtenerDatosFormulario()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene los datos de las distintas categorías necesarias para el formulario
            $tiendas = $this->TiendaModelo->findAll();
            $plataformas = $this->PlataformaModelo->findAll();
            $generos = $this->GeneroModelo->findAll();
            $desarrolladoras = $this->DesarrolladoraModelo->findAll();
            $publishers = $this->PublisherModelo->findAll();

            $response = [
                'tiendas' => $tiendas,
                'plataformas' => $plataformas,
                'generos' => $generos,
                'desarrolladoras' => $desarrolladoras,
                'publishers' => $publishers
            ];
            //Retorna los datos en formato JSON
            return $this->response->setJSON($response);
        } catch (\Exception $e) {
            //Registra el error y retorna una respuesta con estado 500
            log_message('error', 'Error al obtener datos del formulario: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al obtener los datos'
            ])->setStatusCode(500);
        }
    }

    /**
     * Agrega un nuevo videojuego a la base de datos y sube su imagen a Cloudinary.
     *
     * Valida la API key y comprueba si el videojuego ya existe por su nombre.
     * Si no existe, sube la imagen a Cloudinary y almacena su URL segura en la base de datos.
     * Se utiliza una transacción para garantizar la integridad de la inserción.
     */
    public function agregarJuego()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        //Obtiene los datos enviados en la solicitud
        $datos = $this->request->getPost();
        $imagen = $this->request->getFile('imagen');

        try {
            //Verifica si ya existe un videojuego con el mismo nombre
            $juegoExistente = $this->VideojuegoModelo->where('nombre', $datos['nombre'])->first();
            if ($juegoExistente) {
                return $this->response->setJSON(['error' => 'Ya existe un juego con este nombre.'])
                    ->setStatusCode(400);
            }

            //Configura Cloudinary y sube la imagen del juego
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => getenv('CLOUDINARY_API_KEY'),
                    'api_secret' => getenv('CLOUDINARY_API_SECRET'),
                ],
                'url' => [
                    'secure' => true
                ]
            ]);

            //Genera un identificador seguro para la imagen en Cloudinary
            $nombreJuegoSlug = url_title($datos['nombre'], '-', true);

            //Sube la imagen y obtiene la URL segura
            $uploadResult = $cloudinary->uploadApi()->upload($imagen->getTempName(), [
                'public_id' => 'videojuegos/' . $nombreJuegoSlug,
                'overwrite' => true
            ]);

            $rutaImagenFinal = $uploadResult['secure_url'] ?? null;

            if (!$rutaImagenFinal) {
                return $this->response->setJSON(['error' => 'Error al obtener URL segura de imagen.'])
                    ->setStatusCode(500);
            }

            //Construye el array de datos para la inserción en la base de datos
            $insertData = [
                'nombre' => $datos['nombre'],
                'nota_metacritic' => $datos['nota_metacritic'] ?? null,
                'fecha_lanzamiento' => $datos['fecha_lanzamiento'],
                'sitio_web' => $datos['sitio_web'] ?? null,
                'imagen' => $rutaImagenFinal,
                'plataformas_principales' => $datos['plataformas'],
                'desarrolladoras' => $datos['desarrolladoras'],
                'publishers' => $datos['publishers'],
                'tiendas' => $datos['tiendas'],
                'generos' => $datos['generos'],
                'descripcion' => $datos['descripcion'],
                'creado_por_admin' => 1,
            ];

            //Inicia la transacción y guarda el videojuego en la base de datos
            $db = \Config\Database::connect();
            $db->transBegin();

            $juegoId = $this->VideojuegoModelo->insert($insertData);

            if ($juegoId) {
                $db->transCommit();
                return $this->response->setJSON(['mensaje' => 'Juego agregado correctamente.'])
                    ->setStatusCode(201);
            } else {
                $db->transRollback();
                return $this->response->setJSON(['error' => 'Error al agregar el juego.'])
                    ->setStatusCode(500);
            }
        } catch (\Exception $e) {
            //Revierte la transacción si ocurre una excepción
            if (isset($db)) {
                $db->transRollback();
            }
            return $this->response->setJSON(['error' => 'Excepción al agregar juego.', 'detalle' => $e->getMessage()])
                ->setStatusCode(500);
        }
    }

    /**
     * Crea una nueva cuenta de administrador con nombre y contraseña encriptada.
     *
     * Valida la API key y verifica que el nombre de administrador no esté ya registrado.
     * Si el usuario es único, se cifra la contraseña y se almacena el administrador en la base de datos.
     * Se utiliza una transacción para garantizar la integridad de la inserción.
     */
    public function crearAdministrador()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        //Obtiene los datos de la solicitud en formato JSON
        $datos = $this->request->getJSON(true);
        $nombre = $datos['nombre'] ?? null;
        $password = $datos['password'] ?? null;

        //Verifica si ya existe un administrador con el mismo nombre
        $adminExistente = $this->AdministradoresModelo->where('nombre', $nombre)->first();
        if ($adminExistente) {
            return $this->response->setJSON([
                'error' => 'Ya existe un administrador con ese nombre',
                'datos' => []
            ])->setStatusCode(409);
        }

        //Cifra la contraseña y obtiene la fecha actual
        $passwordCifrada = password_hash($password, PASSWORD_DEFAULT);
        $fechaActual = Time::now('Europe/Madrid')->toDateTimeString();

        //Prepara los datos para la inserción en la base de datos
        $data = [
            'nombre' => $nombre,
            'password' => $passwordCifrada,
            'fecha_creacion' => $fechaActual,
            'fecha_ultimo_login' => $fechaActual
        ];

        //Inicia la transacción y guarda el administrador en la base de datos
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $inserted = $this->AdministradoresModelo->insert($data);

            if (!$inserted) {
                throw new \Exception("Error al insertar el administrador en la base de datos");
            }

            $db->transCommit();

            return $this->response->setJSON([
                'mensaje' => 'Cuenta de administrador creada correctamente',
                'datos' => $data
            ])->setStatusCode(201);
        } catch (\Exception $e) {
            //Revierte la transacción si ocurre una excepción
            $db->transRollback();
            return $this->response->setJSON([
                'error' => 'Error al crear el administrador'
            ])->setStatusCode(500);
        }
    }

    /**
     * Edita los datos de un videojuego existente y actualiza su imagen en Cloudinary si se proporciona una nueva.
     *
     * Valida la API key y busca el videojuego en la base de datos. Si existe, se actualizan sus datos y,
     * si se envía una nueva imagen, se reemplaza la anterior en Cloudinary. Se utiliza una transacción para garantizar
     * la integridad de la actualización.
     */
    public function editarJuego()
    {   
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        //Obtiene los datos enviados y la imagen (si se proporciona)
        $datos = $this->request->getPost();
        $imagen = $this->request->getFile('imagen');

        //Busca el videojuego en la base de datos por su ID
        $juegoActual = $this->VideojuegoModelo->find($datos['id']);
        if (!$juegoActual) {
            return $this->response->setJSON(['error' => 'Juego no encontrado.'])->setStatusCode(404);
        }

        //Inicia la transacción
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            //Configura Cloudinary para la gestión de imágenes
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => getenv('CLOUDINARY_API_KEY'),
                    'api_secret' => getenv('CLOUDINARY_API_SECRET'),
                ],
            ]);

            //Si se envió una nueva imagen válida, elimina la anterior y sube la nueva
            if ($imagen && $imagen->isValid() && !$imagen->hasMoved()) {
                if (strpos($juegoActual['imagen'], 'res.cloudinary.com') !== false) {
                    $publicId = $this->extraerPublicIdDesdeUrl($juegoActual['imagen']);
                    if ($publicId) {
                        $cloudinary->uploadApi()->destroy($publicId);
                    }
                }

                $nombreJuegoSlug = url_title($datos['nombre'], '-', true);
                $tempPath = $imagen->getTempName();

                $uploadResult = $cloudinary->uploadApi()->upload($tempPath, [
                    'public_id' => 'videojuegos/' . $nombreJuegoSlug,
                    'overwrite' => true
                ]);

                $rutaImagenFinal = $uploadResult['secure_url'];
            } else {
                $rutaImagenFinal = $juegoActual['imagen'];
            }

            //Construye el array de actualización de datos
            $updateData = [
                'nombre' => $datos['nombre'] ?? $juegoActual['nombre'],
                'nota_metacritic' => $datos['nota_metacritic'] ?? $juegoActual['nota_metacritic'],
                'fecha_lanzamiento' => $datos['fecha_lanzamiento'] ?? $juegoActual['fecha_lanzamiento'],
                'sitio_web' => $datos['sitio_web'] ?? $juegoActual['sitio_web'],
                'imagen' => $rutaImagenFinal,
                'plataformas_principales' => $datos['plataformas'] ?? $juegoActual['plataformas_principales'],
                'desarrolladoras' => $datos['desarrolladoras'] ?? $juegoActual['desarrolladoras'],
                'publishers' => $datos['publishers'] ?? $juegoActual['publishers'],
                'tiendas' => $datos['tiendas'] ?? $juegoActual['tiendas'],
                'generos' => $datos['generos'] ?? $juegoActual['generos'],
                'descripcion' => $datos['descripcion'] ?? $juegoActual['descripcion'],
                'creado_por_admin' => 1
            ];

            //Ejecuta la actualización en la base de datos
            $actualizado = $this->VideojuegoModelo->update($datos['id'], $updateData);

            if ($actualizado) {
                $db->transCommit();
                return $this->response->setJSON([
                    'mensaje' => 'Juego actualizado correctamente.',
                    'datos' => $updateData
                ])->setStatusCode(200);
            } else {
                throw new \Exception('Error al actualizar el juego.');
            }
        } catch (\Exception $e) {
            //Revierte la transacción en caso de excepción
            $db->transRollback();
            return $this->response->setJSON([
                'error' => 'Error al actualizar el juego',
                'detalle' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Obtiene la lista de videojuegos creados por administradores.
     *
     * Valida la API key y consulta la base de datos buscando videojuegos marcados como creados por administradores.
     * Si no se encuentran registros, retorna un error 404; si ocurre una excepción, se registra y se responde con un error 500.
     */
    public function recibirJuegosAdmin()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene la lista de videojuegos creados por administradores
            $juegos = $this->VideojuegoModelo->select('nombre')->where('creado_por_admin', 1)->findAll();

            //Si no hay registros, retorna un error 404
            if (empty($juegos)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron videojuegos creados por administradores'
                ])->setStatusCode(404);
            }

            //Retorna la lista de juegos creados por administradores en formato JSON
            return $this->response->setJSON([
                'juegos' => $juegos
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra y retorna un error en caso de excepción
            log_message('error', 'Error al obtener juegos administrados: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al recuperar los videojuegos creados por los administradores'
            ])->setStatusCode(500);
        }
    }

    /**
     * Realiza una búsqueda de videojuegos cuyo nombre contenga el texto dado
     */
    public function realizarBusqueda()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {  
            //Obtiene los datos de la solicitud en formato JSON
            $data = $this->request->getJSON(true) ?? [];
            $nombre = trim($data['nombre'] ?? '');

            //Busca videojuegos cuyo nombre contenga el texto dado (sin distinción de mayúsculas/minúsculas)
            $juegos = $this->VideojuegoModelo
                ->like('LOWER(nombre)', strtolower($nombre))
                ->findAll();

            //Si no hay resultados, retorna un error 404
            if (empty($juegos)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron juegos que coincidan',
                    'juegos' => []
                ])->setStatusCode(404);
            }

            //Retorna la lista de juegos coincidentes en formato JSON
            return $this->response->setJSON(['juegos' => $juegos])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje de error 500 en caso de excepción
            log_message('error', 'Error al realizar la búsqueda: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al realizar la búsqueda'
            ])->setStatusCode(500);
        }
    }

    /**
     * Realiza una búsqueda de desarrolladoras cuyo nombre contenga el texto proporcionado.
     *
     * Valida la API key y recibe el término de búsqueda desde la solicitud JSON.
     * Filtra las desarrolladoras cuyo nombre coincida parcial o totalmente con el texto ingresado.
     * Si no se encuentran resultados, retorna un error 404. En caso de excepción, se registra y retorna un error 500.
     */
    public function realizarBusquedaDesarrolladoras()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene los datos de la solicitud en formato JSON
            $data = $this->request->getJSON(true) ?? [];
            $nombre = trim($data['nombre'] ?? '');

            //Busca desarrolladoras cuyo nombre contenga el texto dado (sin distinción de mayúsculas/minúsculas)
            $desarrolladoras = $this->DesarrolladoraModelo
                ->like('LOWER(nombre)', strtolower($nombre))
                ->findAll();

            //Si no hay resultados, retorna un error 404
            if (empty($desarrolladoras)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron desarrolladoras que coincidan',
                    'desarrolladoras' => []
                ])->setStatusCode(404);
            }

            //Retorna la lista de desarrolladoras coincidentes en formato JSON
            return $this->response->setJSON([
                'desarrolladoras' => $desarrolladoras
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje de error 500 en caso de excepción
            log_message('error', 'Error al buscar desarrolladoras: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al realizar la búsqueda'
            ])->setStatusCode(500);
        }
    }

    /**
     * Realiza una búsqueda de publishers cuyo nombre contenga el texto proporcionado.
     *
     * Valida la API key y recibe el término de búsqueda desde la solicitud JSON.
     * Filtra los publishers cuyo nombre coincida parcial o totalmente con el texto ingresado.
     * Si no se encuentran resultados, retorna un error 404. En caso de excepción, se registra y retorna un error 500.
     */
    public function realizarBusquedaPublishers()
    {
        //Valida la API key para autorizar la solicitud
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Obtiene los datos de la solicitud en formato JSON
            $data = $this->request->getJSON(true) ?? [];
            $nombre = trim($data['nombre'] ?? '');

            //Busca publishers cuyo nombre contenga el texto dado (sin distinción de mayúsculas/minúsculas)
            $publishers = $this->PublisherModelo
                ->like('LOWER(nombre)', strtolower($nombre))
                ->findAll();

            //Si no hay resultados, retorna un error 404
            if (empty($publishers)) {
                return $this->response->setJSON([
                    'error' => 'No se encontraron publishers que coincidan',
                    'publishers' => []
                ])->setStatusCode(404);
            }

            //Retorna la lista de publishers coincidentes en formato JSON
            return $this->response->setJSON([
                'publishers' => $publishers
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            //Registra el error y retorna un mensaje de error 500 en caso de excepción
            log_message('error', 'Error al buscar publishers: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al realizar la búsqueda'
            ])->setStatusCode(500);
        }
    }
}
