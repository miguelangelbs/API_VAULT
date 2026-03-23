<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AdministradoresModelo;
use App\Models\DesarrolladoraModelo;
use App\Services\ApiKeyValidator;
use App\Models\VideojuegoModelo;
use App\Models\GeneroModelo;
use App\Models\PlataformaModelo;
use App\Models\PublisherModelo;
use App\Models\TiendaModelo;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Cloudinary\Cloudinary;

//Controlador que gestiona la integración con la API externa RAWG y la base de datos
class ApiController extends BaseController
{
    protected $request;
    protected $response;
    public $apiKeyValidator;
    protected $apiKey;

    /*CONSTRUCTOR*/
    public function __construct()
    {
        //Inicialización de validación por API Key y modelos usados
        $this->apiKeyValidator = new ApiKeyValidator();
        $this->VideojuegoModelo = new VideojuegoModelo();
        $this->AdministradoresModelo = new AdministradoresModelo();
        $this->GeneroModelo = new GeneroModelo();
        $this->DesarrolladoraModelo = new DesarrolladoraModelo();
        $this->PlataformaModelo = new PlataformaModelo();
        $this->PublisherModelo = new PublisherModelo();
        $this->TiendaModelo = new TiendaModelo();
        $this->apiKey = getenv('RAWG_API_KEY');
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
     * Obtiene IDs de videojuegos desde la API RAWG
     * Realiza solicitudes paginadas usando cURL y extrae el identificador de cada juego
     * Puedes configurar la cantidad de páginas a consultar ($totalPages) y el tamaño de cada página ($pageSize)
     */
    public function obtenerIdsJuegos_API()
    {   
        //URL de la API en la que consultar los IDs
        $baseUrl = "https://api.rawg.io/api/games";

        //Cantidad de juegos por página
        $pageSize = 20; //20
        
        //Número de páginas que se consultarán
        $totalPages = 1; //25
        $ids = [];

        for ($page = 1; $page <= $totalPages; $page++) {
            $url = "$baseUrl?key={$this->apiKey}&page=$page&page_size=$pageSize";

            //Se inicializa y configura cURL para la solicitud
            $llamada_API = curl_init();
            curl_setopt($llamada_API, CURLOPT_URL, $url);
            curl_setopt($llamada_API, CURLOPT_RETURNTRANSFER, true);

            //Se ejecuta la solicitud y se cierra
            $response = curl_exec($llamada_API);
            curl_close($llamada_API);

            //Decodifica la respuesta en formato JSON
            $data = json_decode($response, true);

            if (isset($data['results'])) {
                foreach ($data['results'] as $juego) {
                    if (isset($juego['id'])) {
                        $ids[] = $juego['id'];
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Consulta la API RAWG por cada juego y guarda su información en la base de datos
     * Recorre un array de IDs, obtiene los detalles de cada juego (incluyendo plataformas, desarrolladoras,
     * publishers, tiendas y géneros) y lo inserta en la base de datos. Si un juego ya existe (por nombre), se omite
     */
    public function rellenarTablaVideojuegos($idsJuegos)
    {
        //URL de la API en la que consultar los juegos
        $baseUrl = "https://api.rawg.io/api/games";

        //Conexión a la base de datos e inicio de transacción
        $db = \Config\Database::connect();
        $db->transBegin();

        //Por cada uno de los ID se realizará una inserción
        foreach ($idsJuegos as $key => $value) {
            
            //Construye la URL para obtener los detalles del juego
            $url = "$baseUrl/$value?key={$this->apiKey}";
            $juegoData = [];

            //Solicita los datos del juego mediante cURL
            $llamada_API = curl_init();
            curl_setopt($llamada_API, CURLOPT_URL, $url);
            curl_setopt($llamada_API, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($llamada_API);
            curl_close($llamada_API);

            //Decodifica la respuesta JSON
            $data = json_decode($response, true);

            //Si el juego ya existe (según el nombre), salta este registro
            if ($this->VideojuegoModelo->where('nombre', $data['name'])->first()) {
                continue;
            }
            
            //Extrae el nombre
            if (isset($data['name'])) {
                $nombre = $data['name'];
            }

            //Extrae la nota
            if (isset($data['metacritic'])) {
                $notaMetacritic =  $data['metacritic'];
            } else {
                $notaMetacritic = null;
            }

            //Extrae la fecha de lanzamiento
            if (isset($data['released'])) {
                $fechaLanzamiento =  $data['released'];
            }

            //Extrae la página web
            if (isset($data['website'])) {
                $sitioWeb =  $data['website'];
            } else {
                $sitioWeb = null;
            }

            //Extrae la imagen
            if (isset($data['background_image'])) {
                $imagen =  $data['background_image'];
            }

            //Extrae y serializa las plataformas
            $plataformas = [];
            if (!empty($data['platforms'])) {
                foreach ($data['platforms'] as $plataforma) {
                    $plataformas[] = [
                        'id' => $plataforma['platform']['id'],
                        'nombre' => $plataforma['platform']['name']
                    ];
                }
            }
            $plataformasJson = json_encode($plataformas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            //Extrae y serializa las desarrolladoras
            $desarrolladoras = [];
            if (!empty($data['developers'])) {
                foreach ($data['developers'] as $dev) {
                    $desarrolladoras[] = [
                        'id' => $dev['id'],
                        'nombre' => $dev['name']
                    ];
                }
            }
            $desarrolladorasJson = json_encode($desarrolladoras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            //Extrae y serializa los publishers
            $publishers = [];
            if (!empty($data['publishers'])) {
                foreach ($data['publishers'] as $publi) {
                    $publishers[] = [
                        'id' => $publi['id'],
                        'nombre' => $publi['name']
                    ];
                }
            }
            $publishersJson = json_encode($publishers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            //Extrae y serializa las tiendas
            $tiendas = [];
            if (!empty($data['stores'])) {
                foreach ($data['stores'] as $tienda) {
                    $tiendas[] = [
                        'id' => $tienda['store']['id'],
                        'nombre' => $tienda['store']['name']
                    ];
                }
            }
            $tiendasJson = json_encode($tiendas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            //Extrae y serializa los generos
            $generos = [];
            if (!empty($data['genres'])) {
                foreach ($data['genres'] as $genero) {
                    $generos[] = [
                        'id' => $genero['id'],
                        'nombre' => $genero['name']
                    ];
                }
            }
            $generosJson = json_encode($generos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            //Extrae la descripción, eliminando las etiquetas HTML
            if (isset($data['description'])) {
                $descripcion =  $data['description'];
                $descripcion = strip_tags($data['description']);
            }

            //Rellenamos el array con los datos a insertar
            $juegoData = [
                'nombre' => $nombre,
                'nota_metacritic' => $notaMetacritic,
                'fecha_lanzamiento' => $fechaLanzamiento,
                'sitio_web' => $sitioWeb,
                'imagen' => $imagen,
                'plataformas_principales' => $plataformasJson,
                'desarrolladoras' => $desarrolladorasJson,
                'publishers' => $publishersJson,
                'tiendas' => $tiendasJson,
                'generos' => $generosJson,
                'descripcion' => $descripcion,
                'creado_por_admin' => 0
            ];

            //Insertamos los datos del juego en la base de datos
            $this->VideojuegoModelo->insert($juegoData);
        }

        //Se confirma la transacción y se finaliza la operación
        $db->transCommit();

        return $this->response->setJSON(['mensaje' => 'Datos cargados en la base de datos correctamente']);
    }

    /**
     * Actualiza datos desde la API externa
     * Elimina los registros existentes (excepto los creados por administradores) y recarga la base de datos
     * con nuevos datos obtenidos de la API RAWG. Se valida la API key y se utiliza una transacción para garantizar
     * la integridad de la actualización
     */
    public function actualizarDatosAPI()
    {   
        //Valida la API key, si la validación falla se devuelve un error
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {

            //Se conecta a la base de datos e inicia la transacción
            $db = \Config\Database::connect();
            $db->transBegin();

            //Elimina los datos existentes (excluyendo los creados por administradores)
            $this->eliminarDatosActualizar();

            //Consulta la API y actualiza las tablas correspondientes
            $idsJuegos = $this->obtenerIdsJuegos_API();
            $this->rellenarTablaVideojuegos($idsJuegos);
            $this->rellenarTablaGeneros();
            $this->rellenarTablaDesarrolladoras();
            $this->rellenarTablaPlataformas();
            $this->rellenarTablaPublishers();
            $this->rellenarTablaTiendas();

            //Se confirma la transacción
            $db->transCommit();

            return $this->response->setJSON([
                'mensaje' => 'Datos actualizados correctamente.'
            ])->setStatusCode(200);

        } catch (\Exception $e) {
            //En caso de excepción se revierte la transacción y registra el error
            if (isset($db)) {
                $db->transRollback();
            }

            log_message('error', 'Error al actualizar datos desde la API externa: ' . $e->getMessage());

            return $this->response->setJSON([
                'error' => 'No se pudo completar la actualización de datos.'
            ])->setStatusCode(500);
        }
    }

    /**
     * Carga y guarda datos de géneros desde la API RAWG
     * Realiza una solicitud GET para obtener géneros y los inserta en la base de datos,
     * omitiendo aquellos que ya estén registrados (según el nombre)
     */
    public function rellenarTablaGeneros()
    {
        //URL de la API para obtener los géneros
        $baseUrl = "https://api.rawg.io/api/genres";

        //Conexión a la base de datos y inicio de transacción
        $db = \Config\Database::connect();
        $db->transStart();

        //Se configura y ejecuta la solicitud cURL
        $url = "$baseUrl?key={$this->apiKey}";
        $llamada_API = curl_init();
        curl_setopt($llamada_API, CURLOPT_URL, $url);
        curl_setopt($llamada_API, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($llamada_API);
        curl_close($llamada_API);

        //Se decodifica la respuesta JSON y verifica que existan resultados
        $data = json_decode($response, true);
        if (empty($data['results'])) {
            return;
        }

        //Recorre cada género y lo inserta si no existe ya en la base de datos
        foreach ($data['results'] as $genero) {

            if ($this->GeneroModelo->where('nombre', $genero['name'])->first()) {
                continue;
            }

            $generoData = [
                'nombre'          => $genero['name'],
                'cantidad_juegos' => $genero['games_count'],
                'imagen'          => $genero['image_background']
            ];

            $this->GeneroModelo->insert($generoData);
        }

        //Se finaliza la transacción
        $db->transComplete();
    }

    /**
     * Carga y guarda desarrolladoras desde la API RAWG
     * Solicita datos paginados de desarrolladoras y los inserta en la base de datos,
     * evitando duplicados basados en el nombre
     */
    public function rellenarTablaDesarrolladoras()
    {
        //URL de la API para obtener las desarrolladoras
        $baseUrl = "https://api.rawg.io/api/developers";

        //Número de páginas que se consultarán
        $totalPages = 1; //25
        
        //Cantidad de desarrolladoras por página
        $pageSize = 40; //40

        //Conecta a la base de datos e inicia la transacción
        $db = \Config\Database::connect();
        $db->transStart();

        //Itera sobre cada página de resultados
        for ($page = 1; $page <= $totalPages; $page++) {
            $url = "$baseUrl?key={$this->apiKey}&page=$page&page_size=$pageSize";
            
            //Inicializa cURL para la solicitud
            $llamada_API = curl_init();
            curl_setopt($llamada_API, CURLOPT_URL, $url);
            curl_setopt($llamada_API, CURLOPT_RETURNTRANSFER, true);

            //Ejecuta la solicitud y cierra cURL
            $response = curl_exec($llamada_API);
            curl_close($llamada_API);

            //Decodifica la respuesta JSON
            $data = json_decode($response, true);

            //Si no hay resultados, termina la función
            if (empty($data['results'])) {
                return;
            }

            //Procesa cada desarrolladora obtenida
            foreach ($data['results'] as $desarrolladora) {

                //Salta si ya existe la desarrolladora (según el nombre)
                if ($this->DesarrolladoraModelo->where('nombre', $desarrolladora['name'])->first()) {
                    continue;
                }

                //Prepara los datos a insertar
                $desarrolladoraData = [
                    'nombre'          => $desarrolladora['name'],
                    'cantidad_juegos' => $desarrolladora['games_count'],
                    'imagen'          => $desarrolladora['image_background']
                ];

                $this->DesarrolladoraModelo->insert($desarrolladoraData);
            }
        }
        //Finaliza la transacción
        $db->transComplete();
    }

    /**
     * Carga y guarda plataformas desde la API RAWG
     * Solicita datos paginados de plataformas y las inserta en la base de datos,
     * evitando duplicados basados en el nombre
     */
    public function rellenarTablaPlataformas()
    {
        //URL de la API para obtener las plataformas
        $baseUrl = "https://api.rawg.io/api/platforms";
        
        //Número de páginas que se consultarán
        $totalPages = 2;

        //Conecta a la base de datos e inicia la transacción
        $db = \Config\Database::connect();
        $db->transStart();

        //Itera sobre cada página de resultados
        for ($page = 1; $page <= $totalPages; $page++) {
            $url = "$baseUrl?key={$this->apiKey}&page=$page";
            
            //Inicializa cURL para la solicitud
            $llamada_API = curl_init();
            curl_setopt($llamada_API, CURLOPT_URL, $url);
            curl_setopt($llamada_API, CURLOPT_RETURNTRANSFER, true);

            //Ejecuta la solicitud y cierra cURL
            $response = curl_exec($llamada_API);
            curl_close($llamada_API);

            //Decodifica la respuesta JSON
            $data = json_decode($response, true);

            //Si no hay resultados, termina la función
            if (empty($data['results'])) {
                return;
            }

            //Procesa cada plataforma obtenida
            foreach ($data['results'] as $plataforma) {

                //Salta si ya existe la plataforma (según el nombre)
                if ($this->PlataformaModelo->where('nombre', $plataforma['name'])->first()) {
                    continue;
                }

                //Prepara los datos a insertar
                $plataformaData = [
                    'nombre'          => $plataforma['name'],
                    'cantidad_juegos' => $plataforma['games_count'],
                    'imagen'          => $plataforma['image_background']
                ];

                $this->PlataformaModelo->insert($plataformaData);
            }
        }
        //Finaliza la transacción
        $db->transComplete();
    }

    /**
     * Carga y guarda publishers desde la API RAWG
     * Solicita datos paginados de publishers y los inserta en la base de datos,
     * evitando duplicados basados en el nombre
     */
    public function rellenarTablaPublishers()
    {   
        //URL de la API para obtener los publishers
        $baseUrl = "https://api.rawg.io/api/publishers";
        
        //Número de páginas que se consultarán
        $totalPages = 1; //25

        //Cantidad de desarrolladoras por página
        $pageSize = 40;

        //Conecta a la base de datos e inicia la transacción
        $db = \Config\Database::connect();
        $db->transStart();

        //Itera sobre cada página de resultados
        for ($page = 1; $page <= $totalPages; $page++) {
            $url = "$baseUrl?key={$this->apiKey}&page=$page&page_size=$pageSize";

            //Inicializa cURL para la solicitud
            $llamada_API = curl_init();
            curl_setopt($llamada_API, CURLOPT_URL, $url);
            curl_setopt($llamada_API, CURLOPT_RETURNTRANSFER, true);

            //Ejecuta la solicitud y cierra cURL
            $response = curl_exec($llamada_API);
            curl_close($llamada_API);

            //Decodifica la respuesta JSON
            $data = json_decode($response, true);

            //Si no hay resultados, termina la función
            if (empty($data['results'])) {
                return;
            }

            //Procesa cada publisher obtenida
            foreach ($data['results'] as $publisher) {

                //Salta si ya existe el publisher (según el nombre)
                if ($this->PublisherModelo->where('nombre', $publisher['name'])->first()) {
                    continue;
                }

                //Prepara los datos a insertar
                $publisherData = [
                    'nombre'          => $publisher['name'],
                    'cantidad_juegos' => $publisher['games_count'],
                    'imagen'          => $publisher['image_background']
                ];

                $this->PublisherModelo->insert($publisherData);
            }
        }

        //Finaliza la transacción
        $db->transComplete();
    }

    /**
     * Carga y guarda tiendas desde la API RAWG
     * Solicita datos paginados de tiendas y las inserta en la base de datos,
     * evitando duplicados basados en el nombre
     */
    public function rellenarTablaTiendas()
    {   
        //URL de la API para obtener las tiendas
        $baseUrl = "https://api.rawg.io/api/stores";

        //Conecta a la base de datos e inicia la transacción
        $db = \Config\Database::connect();
        $db->transStart();

        $url = "$baseUrl?key={$this->apiKey}";
        
        //Inicializa cURL para la solicitud
        $llamada_API = curl_init();
        curl_setopt($llamada_API, CURLOPT_URL, $url);
        curl_setopt($llamada_API, CURLOPT_RETURNTRANSFER, true);

        //Ejecuta la solicitud y cierra cURL
        $response = curl_exec($llamada_API);
        curl_close($llamada_API);

        //Decodifica la respuesta JSON
        $data = json_decode($response, true);

        //Si no hay resultados, termina la función
        if (empty($data['results'])) {
            return;
        }

        //Procesa cada tienda obtenida
        foreach ($data['results'] as $tienda) {

            //Salta si ya existe la tienda (según el nombre)
            if ($this->TiendaModelo->where('nombre', $tienda['name'])->first()) {
                continue;
            }

            //Prepara los datos a insertar
            $tiendaData = [
                'nombre'          => $tienda['name'],
                'dominio'         => $tienda['domain'],
                'cantidad_juegos' => $tienda['games_count'],
                'imagen'          => $tienda['image_background']
            ];

            $this->TiendaModelo->insert($tiendaData);
        }

        //Finaliza la transacción
        $db->transComplete();
    }

    /**
     * Elimina videojuegos no creados por administradores y reinicia los IDs de las tablas.
     * 
     * Elimina los registros de videojuegos que no fueron creados por administradores y
     * reinicia la secuencia de la tabla de videojuegos. Además, trunca y reinicia los IDs
     * de las tablas de géneros, desarrolladoras, plataformas, publishers y tiendas.
     * Se utiliza una transacción para garantizar la integridad de la operación.
     */
    public function eliminarDatosActualizar()
    {   
        //Conecta a la base de datos e inicia la transacción
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            //Elimina videojuegos que no fueron creados por administradores
            $this->VideojuegoModelo->where('creado_por_admin !=', 1)->delete();

            //Reinicia la secuencia de IDs en la tabla de videojuegos
            $db->query('SELECT setval(\'vault."Videojuegos_id_seq"\', (SELECT MAX(id) FROM vault.videojuegos), true);');

            //Trunca y reinicia el ID de las tablas relacionadas
            $db->query('TRUNCATE TABLE vault.generos RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.desarrolladoras RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.plataformas RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.publishers RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.tiendas RESTART IDENTITY CASCADE;');
        } catch (\Exception $e) {
            //Revierte la transacción y registra el error en caso de excepción
            $db->transRollback();
            log_message('error', 'Error al realizar eliminación y actualización: ' . $e->getMessage());
            throw new \Exception('Error en la transacción: ' . $e->getMessage());
        }

        //Finaliza la transacción
        $db->transComplete();

        //Verifica el estado final de la transacción y lanza excepción en caso de fallo
        if ($db->transStatus() === FALSE) {
            log_message('error', 'La transacción falló en eliminarDatosActualizar.');
            throw new \Exception('La transacción falló.');
        }
    }

    /**
     * Resetea y recarga la base de datos, eliminando todos los registros e imágenes de Cloudinary
     *
     * Valida la API key, elimina las imágenes en Cloudinary asociadas a los videojuegos,
     * purga los datos existentes (con eliminación específica) y vuelve a insertar
     * la información obtenida de la API RAWG.
     */
    public function purgarDatos()
    {
        //Valida la API key; si no es válida, retorna el error
        $resultadoValidacion = $this->apiKeyValidator->validar($this->request, $this->response);
        if ($resultadoValidacion !== true) {
            return $resultadoValidacion;
        }

        try {
            //Configura Cloudinary con credenciales del entorno
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => getenv('CLOUDINARY_API_KEY'),
                    'api_secret' => getenv('CLOUDINARY_API_SECRET'),
                ]
            ]);

            //Obtiene todos los videojuegos almacenados
            $juegos = $this->VideojuegoModelo->findAll();

            //Recorre cada juego y elimina la imagen de Cloudinary si corresponde
            foreach ($juegos as $juego) {
                if (isset($juego['imagen']) && strpos($juego['imagen'], 'res.cloudinary.com') !== false) {
                    $publicId = $this->extraerPublicIdDesdeUrl($juego['imagen']);
                    if ($publicId) {
                        try {
                            $cloudinary->uploadApi()->destroy($publicId);
                        } catch (\Exception $e) {
                            log_message('error', 'Error al eliminar imagen de Cloudinary (ID: ' . $publicId . '): ' . $e->getMessage());
                        }
                    }
                }
            }

            //Elimina los datos actuales de la base de datos
            $this->eliminarDatosPurgar();

            //Recarga la información: obtiene IDs y repuebla las tablas correspondientes
            $idsJuegos = $this->obtenerIdsJuegos_API();
            $this->rellenarTablaVideojuegos($idsJuegos);
            $this->rellenarTablaGeneros();
            $this->rellenarTablaDesarrolladoras();
            $this->rellenarTablaPlataformas();
            $this->rellenarTablaPublishers();
            $this->rellenarTablaTiendas();

            return $this->response->setJSON([
                'mensaje' => 'Datos purgados correctamente.'
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            log_message('error', 'Error en purgarDatos: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Error al purgar y recargar los datos.'
            ])->setStatusCode(500);
        }
    }

    /**
     * Trunca todas las tablas del esquema 'vault', reiniciando sus IDs y eliminando los datos.
     *
     * Inicia una transacción para truncar las tablas de videojuegos, géneros, desarrolladoras,
     * plataformas, publishers y tiendas, utilizando CASCADE para limpiar cualquier dato relacionado.
     */
    public function eliminarDatosPurgar()
    {   
        //Conecta a la base de datos
        $db = \Config\Database::connect();

        try {
            //Inicia la transacción
            $db->transStart();

            //Trunca las tablas y reinicia los IDs
            $db->query('TRUNCATE TABLE vault.videojuegos RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.generos RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.desarrolladoras RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.plataformas RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.publishers RESTART IDENTITY CASCADE;');
            $db->query('TRUNCATE TABLE vault.tiendas RESTART IDENTITY CASCADE;');

            //Finaliza la transacción
            $db->transComplete();
        } catch (\Exception $e) {
            //Revierte la transacción y relanza el error en caso de fallo
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Obtiene el AppID de un juego específico desde la API pública de Steam.
     *
     * La función recibe el nombre del juego a buscar a través de una consulta GET, ajusta
     * las directivas de PHP para evitar limitaciones de ejecución y consulta la lista completa
     * de aplicaciones de Steam. Luego, normaliza el nombre del juego y busca una coincidencia
     * exacta en la lista. Si se encuentra, retorna el AppID; de lo contrario, devuelve un error.
     */
    public function obtenerAppId()
    {   
        //Ajusta la configuración de PHP para evitar restricciones en la solicitud
        ini_set("post_max_size", -1);
        ini_set("max_execution_time", -1);
        ini_set("memory_limit", -1);
        ini_set("max_input_time", -1);
        ini_set("max_input_vars", -1);

        //Obtiene el nombre del juego desde la solicitud GET
        $nombreJuego = $this->request->getGet('nombreJuego');

        try {
            //URL de la API de Steam para obtener la lista completa de aplicaciones
            $apiUrl = 'https://api.steampowered.com/ISteamApps/GetAppList/v2/';

            //Inicializa y configura cURL para la solicitud
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            //Ejecuta la solicitud y obtiene la respuesta, código HTTP y cualquier error
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            //Verifica si la solicitud falló o el código HTTP no es 200
            if ($response === false || $httpCode !== 200) {
                log_message('error', 'Error en cURL al llamar a Steam API: ' . $curlError);
                return $this->response->setJSON([
                    'error' => 'No se pudo obtener la lista de juegos desde Steam.'
                ])->setStatusCode(500);
            }

            //Decodifica la respuesta y valida la estructura esperada
            $json = json_decode($response, true);
            if (!$json || !isset($json['applist']['apps'])) {
                return $this->response->setJSON([
                    'error' => 'Estructura de respuesta de Steam no válida.'
                ])->setStatusCode(500);
            }

            //Normaliza el nombre del juego buscado para facilitar la comparación
            $apps = $json['applist']['apps'];
            $nombreJuegoBuscado = $this->normalizarNombre($nombreJuego);

            //Recorre la lista de aplicaciones para encontrar una coincidencia exacta
            foreach ($apps as $app) {
                $nombreJuegoActual = $this->normalizarNombre($app['name'] ?? '');

                if ($nombreJuegoBuscado === $nombreJuegoActual) {
                    return $this->response->setJSON([
                        'appid' => $app['appid']
                    ])->setStatusCode(200);
                }
            }

            //Retorna un error 404 si no se encuentra la coincidencia
            return $this->response->setJSON([
                'error' => 'Gráfica del juego no encontrada'
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            //Registra el error con información detallada y retorna un mensaje genérico de fallo
            log_message('error', 'Error al obtener AppID desde Steam API: ' . $e->getMessage() . ' en línea ' . $e->getLine());
            return $this->response->setJSON([
                'error' => 'Ocurrió un error al buscar el AppID.'
            ])->setStatusCode(500);
        }
    }

    /**
     * Normaliza el nombre de un juego para comparar de forma consistente.
     *
     * Decodifica la cadena (en caso de que esté en formato URL), la convierte a minúsculas,
     * remueve acentos y caracteres especiales, elimina caracteres no alfanuméricos (excepto espacios)
     * y reduce los espacios múltiples a uno solo.
     */
    private function normalizarNombre($nombre)
    {

        $nombre = urldecode($nombre);
        $nombre = trim(mb_strtolower($nombre, 'UTF-8'));
        $nombre = iconv('UTF-8', 'ASCII//TRANSLIT', $nombre);
        $nombre = preg_replace('/[^a-z0-9 ]/', '', $nombre);
        $nombre = preg_replace('/\s+/', ' ', $nombre);

        return $nombre;
    }
}
