<?php

namespace App\Http\Services;

class PointLocation
{
    var bool $pointOnVertex = true; // Check if the point sits exactly on one of the vertices?

    function inPolygon($point, $polygon, $isGeo = true): bool
    {
        $isIn = $this->pointInPolygon($point, $polygon, $isGeo);
        return $isIn != "outside";
    }

    function pointInPolygon($point, $polygon, $isGeo = true): string
    {


        // Transform string coordinates into arrays with x and y values
        $point = $this->pointStringToCoordinates($point, $isGeo);
        $vertices = array();

        foreach ($polygon as $vertex) {
            $vertices[] = $this->pointStringToCoordinates($vertex, $isGeo);
        }
        // Check if the point sits exactly on a vertex
        if ($this->pointOnVertex and $this->pointOnVertex($point, $vertices)) {
            return "vertex";
        }

        // Check if the point is inside the polygon or on the boundary
        $intersections = 0;
        $vertices_count = count($vertices);

        for ($i = 1; $i < $vertices_count; $i++) {
            $vertex1 = $vertices[$i - 1];
            $vertex2 = $vertices[$i];
            if ($vertex1['y'] == $vertex2['y'] and $vertex1['y'] == $point['y'] and $point['x'] > min($vertex1['x'], $vertex2['x']) and $point['x'] < max($vertex1['x'], $vertex2['x'])) { // Check if point is on an horizontal polygon boundary
                return "boundary";
            }
            if ($point['y'] > min($vertex1['y'], $vertex2['y']) and $point['y'] <= max($vertex1['y'], $vertex2['y']) and $point['x'] <= max($vertex1['x'], $vertex2['x']) and $vertex1['y'] != $vertex2['y']) {
                $xinters = ($point['y'] - $vertex1['y']) * ($vertex2['x'] - $vertex1['x']) / ($vertex2['y'] - $vertex1['y']) + $vertex1['x'];
                if ($xinters == $point['x']) { // Check if point is on the polygon boundary (other than horizontal)
                    return "boundary";
                }
                if ($vertex1['x'] == $vertex2['x'] || $point['x'] <= $xinters) {
                    $intersections++;
                }
            }
        }
        // If the number of edges we passed through is odd, then it's in the polygon.
        if ($intersections % 2 != 0) {
            return "inside";
        } else {
            return "outside";
        }
    }

    function pointOnVertex($point, $vertices): bool
    {
        foreach ($vertices as $vertex) {
            if ($point == $vertex) {
                return true;
            }
        }
        return false;
    }

    function pointStringToCoordinates($point, $isGeo): array
    {
        if (is_array($point)) {
            if ($isGeo) {
                return array("x" => $point[1], "y" => $point[0]);
            }
            return array("x" => $point[0], "y" => $point[1]);
        }
        $coordinates = explode(" ", $point);
        return array("x" => $coordinates[0], "y" => $coordinates[1]);
    }


}
