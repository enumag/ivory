/*
V ISS slze používat proměnné. Deklarují se stejně jako CSS vlastnosti, pouze s prefixem ($) před názvem.
Proměnné lze libovolně používat ve výrazech. Platnost proměnných je vždy jen v aktuálním bloku a vnořených blocích.
*/

$width: 100px;

.block {
	width: $width;
	padding: $width / 10 - (2 * 1);
}