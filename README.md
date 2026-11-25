# Práctica 3.2: enrutamiento por contenido y filtrado de mensajes

Backend de Correos – Productos Dañados. Un producto dañado genera eventos de distinto tipo: avisar al remitente, pedir un reembolso o retirar unidades del inventario. Antes todos iban al mismo proceso; ahora un **Message Filter** descarta los que no deben procesarse y un **Content-Based Router** manda cada uno a la cola de su consumidor en RabbitMQ.

```
POST /api/eventos → validación → filtro → enrutador → exchange correos.eventos (direct)
                                                   ├─ notificaciones → consumidor notificaciones
                                                   ├─ reembolsos     → consumidor reembolsos
                                                   ├─ inventario     → consumidor inventario
                                                   └─ no_enrutables  → cola de revisión (tipos desconocidos)
```

## Contrato del mensaje

```json
{
  "id": "evt-001",
  "tipo": "aviso_remitente",
  "prioridad": "alta",
  "datos": { "product_id": 2, "canal": "sms" }
}
```

| Campo | Regla |
| --- | --- |
| `id` | Obligatorio, único, letras, números, `.`, `_` o `-` |
| `tipo` | Obligatorio. Si no lo reconoce ninguna regla, va a `no_enrutables` |
| `prioridad` | Obligatoria: `alta`, `media` o `baja`. Se usa como prioridad AMQP |
| `datos.product_id` | Obligatorio, debe existir |
| `datos.canal` | Opcional: `email` o `sms` |
| `datos.cantidad` | Opcional, entero mayor que 0 |

El `correlation_id` llega en la cabecera `X-Correlation-ID`, igual que en la práctica 3.1, o se genera.

## Reglas

| Condición | Destino o decisión | Por qué |
| --- | --- | --- |
| Falta un campo obligatorio | 422, no se registra | El mensaje no cumple el contrato |
| Producto archivado | Filtrado | Ya no se avisa, reembolsa ni ajusta |
| `solicitud_reembolso` de un daño `leve` | Filtrado | Los daños leves no se reembolsan, se venden con descuento |
| `ajuste_inventario` con más unidades que el stock | Filtrado | No se puede retirar lo que no hay |
| `tipo = aviso_remitente` | `notificaciones` | Avisa al remitente |
| `tipo = solicitud_reembolso` | `reembolsos` | Calcula el monto según el daño |
| `tipo = ajuste_inventario` | `inventario` | Descuenta unidades del stock |
| Cualquier otro tipo | `no_enrutables` | Queda en revisión, no se descarta |
| RabbitMQ no responde | 503 `error_transporte` | Fallo de transporte, no rechazo de negocio |

Las reglas del enrutador están en `config/enrutamiento.php`.

## Piezas

| Archivo | Qué hace |
| --- | --- |
| `app/Http/Requests/EventoRequest.php` | Valida el contrato |
| `app/Enrutamiento/FiltroDeMensajes.php` | Message Filter |
| `app/Enrutamiento/EnrutadorPorContenido.php` | Content-Based Router |
| `app/Enrutamiento/TopologiaEventos.php` | Exchange, colas y routing keys |
| `app/Enrutamiento/RabbitMqPublicadorEventos.php` | Publica con la routing key del destino |
| `app/Enrutamiento/Consumidores/*` | Los tres consumidores |
| `app/Enrutamiento/ProcesadorDeEventos.php` | Comprueba que el mensaje sea del consumidor y lo procesa una vez |
| `app/Http/Controllers/Api/EventoController.php` | Productor: `POST /api/eventos` y seguimiento |
| `app/Console/Commands/ConsumirEventos.php` | `php artisan eventos:consumir {destino}` |
| `app/Console/Commands/RecorridoEventos.php` | `php artisan eventos:recorrido` |
| `tests/Feature/EnrutamientoTest.php` | Los seis casos de la guía y más |

## Cómo ejecutarlo

Se necesita PHP 8.2+ con la extensión `sockets`, Composer y Docker.

```bash
composer install
cp .env.example .env          # completar RABBITMQ_PASSWORD y DEMO_PASSWORD con valores de prueba
php artisan key:generate
php artisan migrate --seed
docker compose up -d          # RabbitMQ
php artisan serve             # productor
php artisan eventos:consumir notificaciones   # una terminal por consumidor
php artisan eventos:consumir reembolsos
php artisan eventos:consumir inventario
```

Para ver el recorrido de los eventos:

```bash
php artisan eventos:recorrido
docker exec correos-rabbitmq rabbitmqctl list_queues name messages_ready consumers
```

Pruebas:

```bash
php artisan test --filter=EnrutamientoTest
```

La notificación asíncrona de la práctica 3.1 sigue disponible en `POST /api/notificaciones`.
