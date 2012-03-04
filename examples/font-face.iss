@font-face($font, $name: $font) {
	@font-face {
		font-family: $name;
		src: url($font . '.eot');
		src: url($font . '.eot?#iefix') format('embedded-opentype'),
			 url($font . '.woff') format('woff'),
			 url($font . '.ttf') format('truetype');
		font-weight: normal;
		font-style: normal;
	}
}

@font-face: 'gfsbodoni-webfont' 'GFSBodoniRegular';
@font-face: 'gfsbodoniboldit-webfont';
@font-face: 'gfsbodoniit-webfont';
@font-face: 'gfsbodonibold-webfont';