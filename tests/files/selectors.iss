div#id.class[attr="value"] {
	$x: raw('.class2');
	~ a {
		&<$x> {
			text-align: justify;
		}
		&.class2 {
			margin-top: 10px;
		}
	}
	& {
		display: block;
		.no-cssgradients > & {
			background-color: red;
		}
	}
}