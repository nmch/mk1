<?php

class test_Arr extends Testcase
{
    function test_merge()
    {
        $arr1 = [1, 2, 3];
        $arr2 = [3, 4, 5];
        $r = Arr::merge($arr1, $arr2);
    }
}
