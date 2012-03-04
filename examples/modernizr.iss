@transform() {
	-webkit-transform: $_argv;
	-moz-transform: $_argv;
	-ms-transform: $_argv;
	-o-transform: $_argv;
	transform: $_argv;
}

div#content {
	.transformed-block {
		@transform: skewX(.85);
		font-size: 22px;
		.no-csstransforms >> & {
			font-size: 18px;
		}
	}
}