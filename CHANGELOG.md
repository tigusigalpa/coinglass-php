# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- **WebSocket API support** for Coinglass's real-time streams, under the new `Tigusigalpa\CoinGlass\WebSocket`
  namespace:
  - `CoinGlassWebSocketClient` / `connect()` — connects to `wss://open-ws.coinglass.com/ws-api`, authenticated via
    the `cg-api-key` query parameter. Accessible from the main SDK via `$client->websocket()` or, in Laravel, via
    `CoinGlass::websocket()` / dependency injection.
  - `CoinGlassStream::subscribe()` / `unsubscribe()` — subscribe to any number of channels over a single connection,
    with the `"ping"`/`"pong"` heartbeat (every 20s) handled automatically.
  - `CoinGlassStream::read()` / `listen()` — pull one message at a time (for integrating with an existing event
    loop) or block forever with a callback (for a CLI worker or queued job).
  - Channel helpers and a typed `Message` value object for every documented stream:
    - `Channels::liquidationOrders()` —
      [Liquidation Order](https://docs.coinglass.com/reference/ws-liquidation-order)
    - `Channels::spotTrades($exchange, $symbol, $minVolumeUsd)` —
      [Spot Trade Order](https://docs.coinglass.com/reference/websocket_spot_trades)
    - `Channels::futuresTrades($exchange, $symbol, $minVolumeUsd)` —
      [Futures Trade Order](https://docs.coinglass.com/reference/websocket_futures_trades)
    - `Channels::futuresTicker($exchange, $symbol)` —
      [Futures Ticker Snapshot](https://docs.coinglass.com/reference/websocket_futures_ticker)
  - `Message::collection()` hydrates the `data` payload into the same `CoinGlassCollection` of `CoinGlassDto`
    records used throughout the REST client.
  - Implemented directly on top of PHP streams (`stream_socket_client`) — no extra Composer dependency (e.g. a
    dedicated WebSocket library) was introduced; only `ext-openssl` is needed for `wss://` connections.
  - New `WebSocketException` (extends `CoinGlassException`) for connection, handshake, and read/write failures.
  - Laravel: `CoinGlassWebSocketClient` is bound in the service provider, and `config/coinglass.php` gained a
    `websocket` block (`COINGLASS_WS_BASE_URL`, `COINGLASS_WS_CONNECT_TIMEOUT`, `COINGLASS_WS_PING_INTERVAL`).
  - Unit tests covering frame encoding/decoding, message parsing/hydration, channel builders, and WebSocket config
    (`tests/Unit/WebSocket/`).

### Documentation

- Added a "WebSocket API" section to the README with a usage example, channel-helper reference table, and Laravel
  usage notes.
- Updated the Features list and Requirements table to mention WebSocket support.

## [1.0.0] - Initial release

### Added

- Framework-agnostic PHP SDK for the Coinglass API v4, covering Futures, Spot, Options, ETF, On-Chain, and
  Indicator endpoints.
- First-class Laravel 10–13 integration: publishable config, a bound singleton client, dependency injection, and a
  `CoinGlass` facade.
- PSR-18 swappable HTTP client (Guzzle by default).
- Automatic retry with exponential backoff for HTTP 429 responses, honoring `Retry-After`.
- Typed responses hydrated into `CoinGlassDto` / `CoinGlassCollection`.
- Clear exception hierarchy: `UnauthorizedException`, `NotFoundException`, `RateLimitException`, `ApiException`.
