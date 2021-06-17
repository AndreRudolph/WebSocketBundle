# WebSocket Bundle Events

The GosWebSocketBundle provides events which can be used to hook into actions performed by the bundle.

## Available Events

- `gos_web_socket.server_launched` is dispatched when the websocket server is launched, listeners receive a `Gos\Bundle\WebSocketBundle\Event\ServerLaunchedEvent` object
- `gos_web_socket.client_connected` is dispatched when a client connects to the websocket server, listeners receive a `Gos\Bundle\WebSocketBundle\Event\ClientConnectedEvent` object
- `gos_web_socket.client_disconnected` is dispatched when a client disconnects from the websocket server, listeners receive a `Gos\Bundle\WebSocketBundle\Event\ClientDisconnectedEvent` object
- `gos_web_socket.client_error` is dispatched when a client connection has an error, listeners receive a `Gos\Bundle\WebSocketBundle\Event\ClientErrorEvent` object
- `gos_web_socket.connection_rejected` is dispatched when a connection is rejected by the websocket server, listeners receive a `Gos\Bundle\WebSocketBundle\Event\ConnectionRejectedEvent` object

## Creating an event listener

To create an event listener, please follow the [Symfony documentation](https://symfony.com/doc/current/event_dispatcher.html).
