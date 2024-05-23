<?php
namespace Wp\Resta\REST\Example;

class Hoge
{
    public Fuga $fuga;
    public function __construct(Fuga $fuga)
    {
        $this->fuga = $fuga;
    }

    public function getHoge(): string
    {
        return 'hoge!!';
    }
}
