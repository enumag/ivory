/*
Bloky lze vnořovat do libovolné úrovně, pro každý blok se použijí i selektory všech rodičovských bloků. Oddělovačem je mezera, případně nic, je-li použit prefix "&" (viz example :hover).
*/

#header {
	color: black;

	.navigation {
		font-size: 12px;
	}
	.logo {
		width: 300px;
		:hover { text-decoration: none; } // .logo :hover
		&:hover { text-decoration: none; } // .logo:hover
	}
}