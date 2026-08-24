# Shared Butterfly integration helpers

Framework-neutral TypeScript utilities for integrations that embed Butterfly.
The package currently owns document constants, embed URL construction,
directional host/editor message types, trusted event parsing, typed message
sending, and conversion between browser message payloads and document bytes.
It also validates Butterfly `version.json` metadata, including the supported
package name and minimum web build number.

Keep host-specific APIs in `integrations/<host>` and add reusable protocol code
here once at least one integration consumes it.
