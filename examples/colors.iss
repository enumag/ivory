/*
Klasické RGB barvy lze v CSS zapisovat snadno pomocí #rrggbb, nicméně chybí podobná syntaxe pro rgba.
ISS takovou intaxi zavádí: #rrggbb-aa nebo #rgb-aa, kde "aa" je desetinná část alpha parametru z klasické CSS funkce rgba(R, G, B, A).
Kromě toho lze použít i Microsoftí syntaxi #rrggbbaa, kde aa je zadané hexadecimálně.
*/

.colors {
    color: #000; //rgb
    background-color: #FF; //short iss rgb
    border-color: #12345678; //microsoft rgba syntax
    &:hover {
        color: #F00-50; //iss rgba
    }
}