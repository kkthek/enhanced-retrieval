<?php

namespace DIQA\FacetedSearch\Util;

use DIQA\FacetedSearch\FacetedSearchUtil;
use DIQA\FacetedSearch\FSSolrSMWDB;
use SMW\DIProperty;

class FacetValueGenerator
{

    private $property;
    private $smwProperty;

    /**
     * FacetValues constructor.
     * @param $property
     */
    public function __construct($property)
    {
        $this->property = $property;
        $this->smwProperty = DIProperty::newFromUserLabel($this->property);
    }

    public function getFacetData()
    {
        $distinctPropertyValues = FacetedSearchUtil::getDistinctPropertyValues($this->property);
        usort($distinctPropertyValues, function ($e1, $e2) {
            return strcmp(strtolower($e1['label']), strtolower($e2['label']));
        });

        return array_map(function ($e) {
            return [
                'facetValue' => $this->getSOLRValueFacet($e['id'], $e['label']),
                'propertyFacet' => $this->getSOLRPropertyFacet($this->property),
                'property' => $this->property,
                'label' => $e['label'] ?? $e['id']
            ];
        }, $distinctPropertyValues);
    }

    public function getSOLRValueFacet($value, $label)
    {
        $smwPropertyType = $this->smwProperty->findPropertyValueType();
        $label = $smwPropertyType === '_wpg' ? "|{$label}" : '';
        $solrPropertyName = FSSolrSMWDB::encodeSOLRFieldNameForValue($this->smwProperty);
        return $solrPropertyName . ":" . self::quoteIfNecessary("{$value}{$label}");
    }

    private function getSOLRPropertyFacet()
    {
        $smwPropertyType = $this->smwProperty->findPropertyValueType();
        $solrProperty = $smwPropertyType === '_wpg' ? 'smwh_properties' : 'smwh_attributes';
        $facetValue = FSSolrSMWDB::encodeSOLRFieldName($this->smwProperty);
        return "$solrProperty:$facetValue";
    }

    public function getFacetsToRemove()
    {
        $results = [];
        $results[] = FSSolrSMWDB::encodeSOLRFieldNameForValue($this->smwProperty) . ":.*";
        $results[] = $this->getSOLRPropertyFacet();
        return $results;
    }

    private static function quoteIfNecessary($value)
    {
        $alphanumeric = preg_match('/^\w+$/', $value) === 1;
        return $alphanumeric ? $value : "\"$value\"";
    }
}