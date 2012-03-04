$min-width: 20em;

@media 'handheld and (min-width: ' . $min-width . '), screen and (min-width: ' . $min-width . ')' {
	body {
		color: blue;
	}
}