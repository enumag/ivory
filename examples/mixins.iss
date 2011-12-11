/*
Mixiny jsou vlastně jakási makra, díky kterým lze zjednodušit opakované psaní spousty řádků (např. kvůli vendor prefixům).
Mohou být i parametrizované, parametry se při volání oddělují mezerou, v případě potřeby i čárkou.

Deklarace:
@nazev-mixinu( parametry ) {
    //vlastnosti, vnořené bloky
}

Volání:
@nazev-mixinu; //bez parametrů
@nazev-mixinu: parametry; //parametry jsou oddělené mezerou
*/

//Příklad použití kvůli vendor prefixům
@ellipsis() {
    white-space: nowrap;
    overflow: hidden;
    -ms-text-overflow: ellipsis;
    -o-text-overflow: ellipsis;
    text-overflow: ellipsis;
    text-overflow: ellipsis-word;
}

//CSS self-clearing
@self-clear() {
    zoom: 1; //IE
    :after {
        content: '';
        display: block;
        clear: both;
    }
}

//Příklad s parametry včetně výchozích hodnot
@size($width: auto, $height: $width) {
    width: $width;
    height: $height;
}

//Ukázky volání mixinů
.block {
    @size: 100px;
    @self-clear;
    span {
        @ellipsis;
    }
}