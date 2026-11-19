<?php
namespace Wp\Resta\REST\Example;

/**
 * 並びの向き。WordPress コアの REST API の order パラメータと同じ値
 */
enum Order: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
