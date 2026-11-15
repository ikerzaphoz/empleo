# Guía paso a paso — Sesión 1: Tu primer proyecto en Symfony 7

**Proyecto:** `empleo.ikerzaphoz.dev`

## Objetivo de la sesión

Construir desde cero, sin magia negra y entendiendo cada pieza, la base profesional de una API para recibir ofertas de empleo desde n8n:

- Crear un proyecto limpio con Symfony 7.2.
- Instalar las herramientas esenciales para persistencia, validación, serialización, pruebas y análisis estático.
- Entender la estructura de carpetas y qué aporta Symfony frente a PHP tradicional.
- Crear la entidad `JobOffer` y una base de datos local SQLite.
- Crear el DTO `IngestJobOfferRequest` para validar la entrada.
- Implementar el caso de uso `IngestJobOfferHandler`, desacoplado del controlador.
- Crear y probar el endpoint `POST /api/offers`.
- Escribir un test automatizado y ejecutar PHPStan.

> Esta guía describe el recorrido de una sesión partiendo de un proyecto limpio. En el repositorio actual ya existen la entidad `JobOffer`, el DTO `IngestJobOfferRequest` y una migración; si trabajas sobre esta copia, revisa esos pasos antes de volver a generar los archivos.

## Paso 1: Crear el proyecto e instalar las dependencias (5 min)

Symfony ofrece distintas plantillas de inicio. Las dos más habituales son:

- **`webapp`:** incluye herramientas para aplicaciones web renderizadas en servidor, como Twig, correo, seguridad, traducciones y activos web. Es una opción práctica para aplicaciones monolíticas tradicionales.
- **`skeleton`:** instala una base mínima de Symfony. Añadimos explícitamente los componentes que necesita la aplicación, una buena opción para una API que expone JSON y tiene consumidores como n8n.

Para esta sesión usamos `skeleton` y añadimos las dependencias de forma explícita.

Requisitos: PHP 8.2 o superior, Composer y la extensión `pdo_sqlite` de PHP para trabajar con SQLite.

Desde el directorio donde quieras crear el proyecto (por ejemplo, `D:\Programacion\Repositorios`), ejecuta:

```bash
composer create-project symfony/skeleton:"7.2.*" empleo
cd empleo
```

Instala las dependencias de la aplicación:

```bash
# Persistencia, validación y conversión entre JSON y objetos PHP
composer require symfony/orm-pack symfony/validator symfony/serializer-pack

# Generadores de código para desarrollo
composer require --dev symfony/maker-bundle

# Pruebas y análisis estático
composer require --dev symfony/test-pack phpstan/phpstan phpstan/phpstan-symfony
```

### ¿Qué aporta cada paquete?

- **`symfony/orm-pack`:** instala Doctrine ORM y las migraciones. Doctrine mapea clases PHP a tablas y las migraciones versionan los cambios del esquema.
- **`symfony/validator`:** permite declarar reglas de validación con atributos como `#[Assert\NotBlank]`.
- **`symfony/serializer-pack`:** instala el Serializer, que convierte datos como JSON en objetos PHP y viceversa.
- **`symfony/maker-bundle` (desarrollo):** añade comandos `make:*` para generar código inicial siguiendo las convenciones de Symfony.
- **`symfony/test-pack` (desarrollo):** reúne las herramientas habituales para pruebas, incluidas PHPUnit y dependencias de pruebas HTTP.
- **PHPStan y `phpstan-symfony` (desarrollo):** analizan el código sin ejecutarlo y ayudan a detectar incompatibilidades de tipos y otros errores.

## Paso 2: Entender qué ha creado Symfony (5 min)

A diferencia de una aplicación PHP tradicional, donde distintos archivos pueden estar accesibles directamente por URL, Symfony usa el patrón **Front Controller**: las peticiones HTTP dinámicas entran por un punto de entrada común y el framework las dirige al controlador correspondiente.

| Carpeta / archivo | PHP tradicional | Symfony 7 |
| --- | --- | --- |
| `public/index.php` | Puede haber varios scripts `.php` accesibles por URL. | Front Controller: recibe las peticiones dinámicas y las entrega al runtime y al Kernel. Los recursos públicos también pueden servirse desde `public/`. |
| `src/` | El código suele cargarse con `require` o `require_once`. | Contiene el código de la aplicación. Composer relaciona el espacio de nombres `App\` con esta carpeta. |
| `src/Controller/` | Los scripts suelen mezclar entrada, lógica y respuesta. | Los controladores reciben una `Request`, coordinan servicios y devuelven una `Response`. |
| `src/Entity/` | Se pueden usar arrays y consultas SQL manuales para representar datos. | Contiene clases que Doctrine puede mapear a tablas de la base de datos. |
| `src/Repository/` | Las consultas PDO pueden estar repartidas por la aplicación. | Organiza las consultas relacionadas con una entidad y permite añadir consultas propias. |
| `src/Kernel.php` | La inicialización puede estar distribuida en archivos globales. | Participa en el ciclo de vida de Symfony y en la carga de configuración y bundles. |
| `config/` | Es habitual definir constantes o incluir archivos propios. | Agrupa la configuración de Symfony y sus dependencias, normalmente en YAML o PHP. |
| `bin/console` | No suele haber una interfaz estándar para tareas de gestión. | Permite ejecutar comandos de desarrollo y administración, como `make:entity` o `debug:router`. |
| `.env` | La configuración puede quedar escrita directamente en el código. | Define valores predeterminados para variables de entorno. Los valores locales pueden ir en `.env.local`; no guardes secretos de producción en archivos versionados. |

En esta aplicación, `config/routes.yaml` importa las rutas declaradas con atributos en los controladores. `config/services.yaml` activa el autowiring y registra las clases de `src/` como servicios.

El recorrido básico de una petición es:

```text
Petición HTTP → public/index.php → Kernel → ruta → controlador → Response
```

## Paso 3: Configurar SQLite y crear la entidad `JobOffer` (15 min)

SQLite permite desarrollar localmente sin levantar un servidor de base de datos. Comprueba que PHP tiene disponible `pdo_sqlite` (por ejemplo, con `php -m`).

Para mantener la configuración personal fuera del repositorio, crea o modifica `.env.local` y establece:

```dotenv
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

Genera la entidad con Maker:

```bash
php bin/console make:entity JobOffer
```

Añade estos campos cuando el asistente los solicite:

| Campo | Tipo | Restricciones |
| --- | --- | --- |
| `title` | `string` | longitud 255, obligatorio |
| `company` | `string` | longitud 255, obligatorio |
| `url` | `string` | longitud 500, obligatorio |
| `description` | `text` | obligatorio |
| `matchScore` | `integer` | opcional |
| `status` | `string` | longitud 50, obligatorio |
| `createdAt` | `datetime_immutable` | obligatorio |

Genera y aplica la migración:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

`make:migration` compara el mapeo de las entidades con el esquema actual y genera un archivo versionado en `migrations/`. `doctrine:migrations:migrate` aplica las migraciones pendientes; con SQLite se crea el archivo de base de datos local al establecer la conexión.

### Pregunta para NotebookLM

> Acabo de crear mi primera entidad `JobOffer` en Symfony 7 con Doctrine y he lanzado `make:migration`. Explícame qué diferencia hay entre el patrón Data Mapper que usa Doctrine frente al Active Record tradicional o hacer `INSERT`/`UPDATE` directos, y por qué la entidad no tiene un método `$offer->save()`.

## Paso 4: Crear el DTO de entrada para recibir datos de n8n (15 min)

En vez de pasar arrays asociativos sin tipos por la aplicación, mapeamos la entrada a un **Data Transfer Object (DTO)**. El DTO agrupa los datos que recibe el caso de uso y declara sus reglas de validación.

Crea `src/JobOffer/Application/DTO/IngestJobOfferRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\JobOffer\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class IngestJobOfferRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'El título es obligatorio')]
        #[Assert\Length(max: 255)]
        public string $title,

        #[Assert\NotBlank(message: 'La empresa es obligatoria')]
        #[Assert\Length(max: 255)]
        public string $company,

        #[Assert\NotBlank(message: 'La URL es obligatoria')]
        #[Assert\Url(requireTld: true, message: 'Debe ser una URL válida')]
        #[Assert\Length(max: 500)]
        public string $url,

        #[Assert\NotBlank(message: 'La descripción no puede estar vacía')]
        public string $description,

        #[Assert\Range(min: 0, max: 100)]
        public ?int $matchScore = null,
    ) {
    }
}
```

`readonly` impide modificar las propiedades una vez construido el objeto. Los atributos `#[Assert\...]` describen las reglas que aplicará el Validator. En el endpoint, `#[MapRequestPayload]` hará que Symfony deserialice el JSON y valide el DTO antes de ejecutar el método del controlador. Si el objeto se puede construir pero incumple las restricciones, la respuesta predeterminada es HTTP 422; errores de JSON o de deserialización pueden producir una respuesta HTTP 400.

## Paso 5: Crear el caso de uso `IngestJobOfferHandler` (15 min)

El controlador debería ocuparse del protocolo HTTP, no de las reglas de negocio. El caso de uso recibe el DTO, crea la entidad y coordina su persistencia.

Crea `src/JobOffer/Application/IngestJobOfferHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\JobOffer\Application;

use App\Entity\JobOffer;
use App\JobOffer\Application\DTO\IngestJobOfferRequest;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class IngestJobOfferHandler
{
    public function __construct(
        private JobOfferRepository $jobOfferRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(IngestJobOfferRequest $request): JobOffer
    {
        // Evitar duplicados por URL en el flujo habitual de ingestión.
        $existingOffer = $this->jobOfferRepository->findOneBy(['url' => $request->url]);
        if ($existingOffer !== null) {
            return $existingOffer;
        }

        $offer = new JobOffer();
        $offer->setTitle($request->title);
        $offer->setCompany($request->company);
        $offer->setUrl($request->url);
        $offer->setDescription($request->description);
        $offer->setMatchScore($request->matchScore);
        $offer->setStatus('new');
        $offer->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        return $offer;
    }
}
```

El repositorio consulta si ya existe la URL y el `EntityManager` persiste los cambios. La comprobación evita duplicados en el flujo normal; para garantizarlo también ante peticiones simultáneas, más adelante se puede añadir una restricción única en la base de datos. En este primer ejemplo, el controlador responde `201` tanto si se crea una oferta como si se devuelve una ya existente; una mejora posterior sería representar explícitamente ambos resultados.

### Inspeccionar el autowiring desde la consola

`config/services.yaml` registra las clases de `src/` como servicios y activa `autowire`. Symfony usa los tipos declarados en el constructor para resolver sus dependencias. Estos comandos permiten inspeccionar lo que el contenedor puede resolver y cómo está construido el handler:

```bash
# Ver los tipos que Symfony puede autoconectar
php bin/console debug:autowiring

# Buscar la dependencia de Doctrine
php bin/console debug:autowiring EntityManagerInterface

# Ver los argumentos inyectados en el caso de uso
php bin/console debug:container 'App\JobOffer\Application\IngestJobOfferHandler' --show-arguments
```

Son comandos de diagnóstico: no hay que ejecutarlos para que el autowiring funcione. La clase se llama `App\JobOffer\Application\IngestJobOfferHandler`; no está bajo `App\MessageHandler` ni usa Symfony Messenger.

### Pregunta para NotebookLM

> En mi `IngestJobOfferHandler` he inyectado `JobOfferRepository` y `EntityManagerInterface` en el constructor sin configurar cada clase en `services.yaml`. Explícame cómo funciona el Service Container y el autowiring en Symfony 7 para resolver estas dependencias automáticamente.

## Paso 6: Crear el endpoint REST `POST /api/offers` (15 min)

El controlador invocable recibe la petición, delega en el caso de uso y devuelve una respuesta JSON.

Crea `src/Controller/Api/IngestJobOfferController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\JobOffer\Application\DTO\IngestJobOfferRequest;
use App\JobOffer\Application\IngestJobOfferHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class IngestJobOfferController extends AbstractController
{
    #[Route('/api/offers', name: 'api_offers_ingest', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] IngestJobOfferRequest $request,
        IngestJobOfferHandler $handler,
    ): JsonResponse {
        $offer = $handler->handle($request);

        return $this->json([
            'id' => $offer->getId(),
            'title' => $offer->getTitle(),
            'status' => $offer->getStatus(),
        ], Response::HTTP_CREATED);
    }
}
```

`#[MapRequestPayload]` indica que Symfony debe convertir el cuerpo JSON en un `IngestJobOfferRequest` y validarlo antes de llamar a `__invoke()`. La importación de rutas en `config/routes.yaml` permite descubrir el atributo `#[Route]`.

### Probar el endpoint

En una terminal, arranca el servidor PHP integrado:

```bash
php -S localhost:8000 -t public
```

En otra terminal, envía una petición desde Windows PowerShell. `ConvertTo-Json` construye el cuerpo y `Invoke-RestMethod` envía la solicitud:

```powershell
$body = @{
  title = 'Senior PHP Symfony Developer'
  company = 'Tech Spain'
  url = 'https://ejemplo.com/oferta-1'
  description = 'Buscamos desarrollador Symfony 7 con experiencia'
  matchScore = 90
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8000/api/offers' -Method Post -ContentType 'application/json' -Body $body
```

La respuesta HTTP esperada es `201 Created`. PowerShell muestra el JSON como un objeto, normalmente en forma de tabla; `status` es el estado de la oferta y no el código HTTP:

```json
{
  "id": 1,
  "title": "Senior PHP Symfony Developer",
  "status": "new"
}
```

## Paso 7: Ejecutar PHPStan y escribir el primer test (15 min)

### Análisis estático con PHPStan

Ejecuta el análisis del código de la aplicación en nivel 8:

```bash
vendor/bin/phpstan analyse src --level=8
```

El parámetro `--level=8` selecciona este nivel para esa ejecución. Corrige los errores reales de tipos, pero no elimines campos que Doctrine necesita para mapear la entidad solo para silenciar PHPStan.

Por ejemplo, el identificador autogenerado de `JobOffer` debe conservarse como `?int`: es `null` antes de persistir la entidad y Doctrine le asigna su valor al guardarla. PHPStan no ve esa asignación realizada por el ORM y puede mostrar `property.unusedType`. En ese caso, conserva el campo, sus atributos `#[ORM\Id]` y `#[ORM\GeneratedValue]`, y añade una excepción limitada a ese aviso en `phpstan.dist.neon`:

```neon
parameters:
    ignoreErrors:
        -
            identifier: property.unusedType
            path: src/Entity/JobOffer.php
            count: 1
```

Este bloque se añade dentro de `parameters` junto a la configuración existente. La ruta y `count: 1` limitan la excepción al aviso esperado en esa entidad; no desactives el análisis globalmente. Puedes validar también que Doctrine reconoce el mapeo con:

```bash
php bin/console doctrine:schema:validate
```

### Preparar la base de datos de pruebas

Mantén la base de datos de pruebas separada de la local. Crea `.env.test.local` (no lo subas al repositorio) con una URL SQLite propia:

```dotenv
DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"
```

Aplica las migraciones en el entorno de pruebas:

```bash
php bin/console doctrine:migrations:migrate --env=test
```

### Test funcional del endpoint

Crea `tests/Controller/Api/IngestJobOfferControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngestJobOfferControllerTest extends WebTestCase
{
    public function testIngestJobOfferSuccessfully(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/offers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => 'Backend Developer',
                'company' => 'Acme Corp',
                'url' => 'https://example.com/job/123',
                'description' => 'Descripción de prueba',
                'matchScore' => 85,
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(201);

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertJson($content);
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('new', $payload['status']);
    }

    public function testIngestJobOfferValidationFailure(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/offers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => '',
                'company' => 'Acme Corp',
                'url' => 'not-a-valid-url',
                'description' => '',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(422);
    }
}
```

Ejecuta la suite:

```bash
php bin/phpunit
```

El primer test comprueba que la oferta se acepta y que la respuesta contiene el estado esperado. El segundo envía valores que se pueden deserializar pero incumplen las restricciones, y comprueba la respuesta HTTP `422`.

Al ejecutar PHPUnit, Symfony puede mostrar un registro `[error]` con `UnprocessableEntityHttpException` y los mensajes de validación. Es esperado: corresponde a la petición inválida del segundo test. Si el resumen final muestra `OK` (por ejemplo, `OK (2 tests, 6 assertions)`), la suite ha pasado; solo hay un fallo si PHPUnit termina con `FAILURES!` o `ERRORS!`.
