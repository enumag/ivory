@font-face($font, $name: $font, $weight: normal, $style: normal) {
	@font-face {
		font-family: $name;
		src: url($font . '.eot');
		src: url($font . '.eot?#iefix') format('embedded-opentype'),
			 url($font . '.woff') format('woff'),
			 url($font . '.ttf') format('truetype');
		font-weight: $weight;
		font-style: $style $undefined;
	}
}