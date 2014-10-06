<?php

namespace UniMapper\Flexibee;

use UniMapper\Reflection;

class Mapping extends \UniMapper\Mapping
{

    const DATETIME_FORMAT = "Y-m-d\TH:i:sP";

    public function mapValue(Reflection\Entity\Property $property, $value)
    {
        if ($property->isAssociation()
            && $property->getAssociation() instanceof Reflection\Entity\Property\Association\OneToOne
            && !empty($value)
        ) {
            $value = $value[0];
        }

        return parent::mapValue($property, $value);
    }

    public function mapEntity(Reflection\Entity $entityReflection, $data)
    {
        if (isset($data->{"external-ids"}) && isset($data->id)) {
            // Replace id value with 'code:...' from external-ids automatically

            foreach ($data->{"external-ids"} as $externalId) {

                if (substr($externalId, 0, 5) === "code:") {

                    $data->id = $externalId;
                    break;
                }
            }
        }

        return parent::mapEntity($entityReflection, $data);
    }

    public function unmapValue(Reflection\Entity\Property $property, $value)
    {
        $value = parent::unmapValue($property, $value);
        if ($value === null) {
            $value = "";
        } elseif ($value instanceof \DateTime) {
            $value = $value->format(self::DATETIME_FORMAT);
        }
        return $value;
    }

    public function unmapOrderBy(
        array $items,
        Reflection\Entity $entityReflection = null
    ) {
        $result = [];
        foreach ($items as $name => $direction) {

            if ($direction === "asc") {
                $direction = "A";
            } else {
                $direction = "D";
            }
            $result[] = "order=" . rawurlencode($entityReflection->getProperty($name)->getMappedName()  . "@" . $direction);
        }
        return $result;
    }

    public function unmapConditions(array $conditions, Reflection\Entity $entityReflection = null)
    {
        $result = "";

        foreach ($conditions as $condition) {

            if (is_array($condition[0])) {
                // Nested conditions

                list($nestedConditions, $joiner) = $condition;
                $converted = "(" . $this->unmapConditions($nestedConditions, $entityReflection) . ")";
                // Add joiner if not first condition
                if ($result !== "") {
                    $result .= " " . $joiner . " ";
                }
                $result .= $converted;

            } else {
                // Simple condition

                list($propertyName, $operator, $value, $joiner) = $condition;

                // Value
                if (is_array($value)) {
                    $value = "('" . implode("','", $value) . "')";
                } elseif ($value instanceof \DateTime) {
                    $value = "'" . $value->format(self::DATETIME_FORMAT) . "'";
                } else {
                    $leftPercent = $rightPercent = false;
                    if (substr($value, 0, 1) === "%") {
                        $value = substr($value, 1);
                        $leftPercent = true;
                    }
                    if (substr($value, -1) === "%") {
                        $value = substr($value, 0, -1);
                        $rightPercent = true;
                    }
                    $value = "'" . $value . "'";
                }

                // Compare
                if ($operator === "COMPARE") {
                    if ($rightPercent && !$leftPercent) {
                        $operator = "BEGINS";
                    } elseif ($leftPercent && !$rightPercent) {
                        $operator = "ENDS";
                    } else {
                        $operator = "LIKE SIMILAR";
                    }
                }

                // IS, IS NOT
                if (($operator === "IS NOT" || $operator === "IS") && $value === "''") {
                    $value = "empty";
                }

                // Map property name if needed
                if ($entityReflection) {
                    $propertyName = $entityReflection->getProperty($propertyName)->getMappedName();
                }

                $formatedCondition = $propertyName . " " . $operator . " " . $value;

                // Check if is it first condition
                if ($result !== "") {
                    $result .= " " . $joiner . " ";
                }

                $result .=  $formatedCondition;
            }
        }

        return $result;
    }

    public function unmapSelection(
        array $selection,
        Reflection\Entity $entityReflection = null
    ) {
        return implode(
            ",",
            $this->escapeProperties(
                parent::unmapSelection($selection, $entityReflection)
            )
        );
    }

    /**
     * Escape properties with @ char (polozky@removeAll), @showAs, @ref ...
     *
     * @param array $properties
     *
     * @return array
     */
    public function escapeProperties(array $properties)
    {
        foreach ($properties as $index => $item) {

            if ($this->endsWith($item, "@removeAll")) {
                $properties[$index] = substr($item, 0, -10);
            } elseif ($this->endsWith($item, "@showAs") || $this->endsWith($item, "@action")) {
                $properties[$index] = substr($item, 0, -7);
            } elseif ($this->endsWith($item, "@ref")) {
                $properties[$index] = substr($item, 0, -4);
            }
        }
        return $properties;
    }

    public function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

}