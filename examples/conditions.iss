@mixin($param1, $param2) {
    if ($_argc = 0) {
        display: none;
    } elseif ($_argc = 1) .ie6 >> > span {
        color: $param1;
    } else &:hover {
        color: $param1;
        font-size: $param2;
    }
}

.class1 {
    @mixin;
}
.class2 {
    @mixin: #000;
}
.class3 {
    @mixin: #F00-50 1.5rem;
}