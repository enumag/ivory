/*
Na výstupu se každý selektor objeví pouze jednou.
Pozor na priority pravidel!
*/

div {
	> a {
		color: black;
	}
}

a {
	font-size: 2rem;
}

div > a {
	background-color: white;
}