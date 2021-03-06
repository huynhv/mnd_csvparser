<?php
namespace Parser;

include 'csvparser.php';

/**
 * Subclass for parsing modifiers CSV data
 * @author Vy Huynh
 */
class ModsParser extends CSVParser
{

    public function __construct($file)
    {
        parent::__construct($file);
    }

    /**
     * Validate CSV Data by checking for: (1) empty file, and (2) missing important values
     * @return [type] [description]
     */
    public function validate()
    {
        $data   = $this->csv_data;
        $errors = $this->errors;

        $numRows = count($data);
        if (!empty($data)) {
            foreach ($data as $rowIndex => $row) {
                $emptyElems = 0;
                $row_errors = array();
                foreach ($row as $key => $elem) {
                    // trim trailing spaces
                    trim($elem);

                    // check for empty important values Group and Item
                    if (empty($elem)) {
                        $emptyElems++;
                        if ($key == 'Group') {
                            $row_errors[2][] = $key;
                        }

                        if ($key == 'Item') {
                            $row_errors[2][] = $key;
                        }
                    }

                    // check for unallowed topping types
                    if ($key == 'Type') {
                        $elem = strtolower($elem);
                        if (!empty($elem)) {
                            if (!in_array($elem, $this->allowed_topping_types)) {
                                $row_errors[3]       = $key;
                                $this->errors_code[] = 3; // record error: unallowed topping types
                            }

                        }
                    }
                }

                if ($emptyElems == count($row)) {
                    // remove empty rows
                    unset($data[$rowIndex]);
                } elseif ($emptyElems > 0 && count($row_errors) > 0) {
                    if (!in_array(2, $this->errors_code)) {
                        $this->errors_code[] = 2; // record error: empty important values
                    }
                    $errors[$rowIndex] = $row_errors;
                }
            }

            $data = array_values($data);
        } else {
            $this->errors_code[] = 1; // record error: empty data
        }

        $this->csv_data = $data;
        $this->errors   = $errors;

        return (count($this->errors) == 0);
    }

    public function duplicateGroup($group)
    {
        $query =
        "SELECT *
            FROM  cs_toppinggroup
            WHERE locationid = :locationid
            AND toppinggroupname = " . $this->dbo->quote($group);
        $stmt = $this->dbo->prepare($query);
        $stmt->bindParam(':locationid', $this->location_id);
        // $stmt->bindParam(':group', $group);
        $stmt->execute();
        $res = $stmt->fetchAll();

        if (count($res) > 0) {
            // should have only one duplicate
            return $res[0]['toppinggroupid'];
        } else {
            return -1;
        }
    }

    public function duplicateItem($item, $group_id)
    {

        $query =
        "SELECT *
            FROM  cs_toppingitems
            WHERE toppinggroupid = :groupid
            AND toppingitemname = " . $this->dbo->quote($item);

        $stmt = $this->dbo->prepare($query);
        $stmt->bindParam(':groupid', $group_id);
        // $stmt->bindParam(':item', $item);

        $stmt->execute();
        $res = $stmt->fetchAll();
        // echo "<pre>" . $item . $group_id . "</pre>";
        // print_r(count($res));

        if (count($res) > 0) {
            // should have only one duplicate
            return $res[0]['topping_id'];
        } else {
            return -1;
        }
    }

    // /**
    //  * Check whether unique label exists in database
    //  * @return [type] [description]
    //  */
    // public function duplicateLabel($group_id, $label)
    // {
    //     $query =
    //     "SELECT *
    //         FROM  cs_custom_topping_labels
    //         WHERE topping_group_id = :groupid
    //         AND topping_label" . $this->dbo->quote($label);

    //     $stmt = $this->dbo->prepare($query);
    //     $stmt->bindParam(':groupid', $group_id);

    //     $stmt->execute();
    //     $res = $stmt->fetchAll();

    //     if (count($res) > 0) {
    //         // should have only one duplicate
    //         return $res[0]['custom_topping_label_id'];
    //     } else {
    //         return -1;
    //     }
    // }

    /**
     * Check whether labels for a group exist. Labels for a group exist
     * only when there are exactly 03 entries in the database.
     * @param  [type] $group_id [id of group to check]
     * @return [type]           []
     */
    public function labelsForGroupExist($group_id)
    {
        $query =
            "SELECT *
            FROM  cs_custom_topping_labels
            WHERE topping_group_id = :groupid";

        $stmt = $this->dbo->prepare($query);
        $stmt->bindParam(':groupid', $group_id);

        $stmt->execute();
        $res = $stmt->fetchAll();

        if (count($res) > 0) {
            return $res; // return the number of entries
        } else {
            return -1;
        }
    }

    /**
     * @inheritDoc
     */
    public function insert()
    {
        $count = count($this->csv_data);

        $topping_item = array(); // track type of modifier group, format: group name => type

        for ($i = 0; $i < $count; $i++) {
            $row           = $this->csv_data[$i];
            $name          = $row['Group'];
            $min           = $row['Min'];
            $max           = $row['Max'];
            $type          = $row['Type'];
            $desc          = $row['Item Description'];
            $qty_item      = $row['Qty for 1 item'];
            $item          = $row['Item'];
            $price         = $row['Main Price'];
            $left          = $row['Left Price'];
            $whole         = $row['Whole Price'];
            $right         = $row['Right Price'];
            $extra_mul     = $row['Extra Multiplied By'];
            $custom_labels = $row['Custom Labels'];

            $main_pos  = $row['Main POS ID'];
            $none_pos  = $row['None POS ID'];
            $left_pos  = $row['Left POS ID'];
            $right_pos = $row['Right POS ID'];
            $whole_pos = $row['Whole POS ID'];
            $extra_pos = $row['Extra POS ID'];

            $topping_item[$name] = $type;
            // format group type
            $type = strtolower($type);

            if ($type == "custom") {
                $type = "custom_topping";
            } elseif ($type == "pizza") {
                $type = "half_topping";
            } elseif ($type == "dropdown") {
                $type = "select";
            }

            if (isset($extra_mul)) {
                $extra_mul = 1;
            } else {
                $extra_mul = 0;
            }

            if (empty($price)) {
                $price = 0;
            }

            // insert mod groups
            $gate_check_group = $this->duplicateGroup($name);
            if ($gate_check_group == -1) {

                $stmt = $this->dbo->prepare(
                    "INSERT INTO `cs_toppinggroup`
                        (
                        `toppinggroupname`,
                        `mintop`,
                        `maxtop`,
                        `group_type`,
                        `locationid`,
                        `quantity_sign_total_modifiers`
                        )
                    VALUES
                        (
                    " . $this->dbo->quote($name) . ",
                        :min,
                        :max,
                    " . $this->dbo->quote($type) . ",
                        :locationid,
                        :qty_item
                        )
                ");

                if ($type == "select" || $type == "radio") {
                    $min = 1;
                    $max = 1;
                }

                // $stmt->bindParam(':name', $name);
                $stmt->bindParam(':min', $min);
                $stmt->bindParam(':max', $max);
                // $stmt->bindParam(':type', $type);
                $stmt->bindParam(':qty_item', $qty_item);
                $stmt->bindParam(':locationid', $this->location_id);

                $stmt->execute();
                // $this->inserted_group[] = $name;
                $this->group_id[$name] = $this->dbo->lastInsertId();
            } else {
                $this->group_id[$name] = $gate_check_group;
            }
            $group_id = $this->group_id[$name];

            // insert mod items
            $gate_check_item = $this->duplicateItem($item, $group_id);
            if ($gate_check_item == -1) {
                $query =
                "INSERT INTO `cs_toppingitems`
                        (
                        `toppinggroupid`,
                        `toppingitemname`,
                        `sequence`,
                        `allow_extra`,
                        `description`,
                        `pos_name`,
                        `pos_name_none`,
                        `pos_name_left`,
                        `pos_name_right`,
                        `pos_name_whole`,
                        `pos_name_extra`
                        )
                    VALUES
                        (
                        :groupid,
                    " . $this->dbo->quote($item) . ",
                        :sequence,
                        :extra_mul,
                    " . $this->dbo->quote($description) . ",
                    " . $this->dbo->quote($main_pos) . ",
                    " . $this->dbo->quote($none_pos) . ",
                    " . $this->dbo->quote($left_pos) . ",
                    " . $this->dbo->quote($right_pos) . ",
                    " . $this->dbo->quote($whole_pos) . ",
                    " . $this->dbo->quote($extra_pos) . "
                        )
                    ";

                $stmt = $this->dbo->prepare($query);

                $sequence = count($this->inserted_item) + 1;

                $stmt->bindParam(':groupid', $group_id);
                // $stmt->bindParam(':name', $item);
                // $stmt->bindParam(':price', $price);
                $stmt->bindParam(':sequence', $sequence);
                $stmt->bindParam(':extra_mul', $extra_mul);

                $stmt->execute();
                $this->inserted_item[] = $item;
                $this->item_id[$item]  = $this->dbo->lastInsertId();
            } else {
                $this->item_id[$item] = $gate_check_item;
                $item_id              = $this->item_id[$item];
                $update_price         =
                    "UPDATE cs_toppingitems
                    SET toppingitemprice = $price
                    WHERE topping_id = " . $item_id;
                $this->dbo->query($update_price);

                if (!in_array($item, $this->inserted_item)) {
                    // update item description
                    $update_item =
                    "UPDATE cs_toppingitems
                    SET description = " . $this->dbo->quote($item_desc) . ",
                        pos_name = " . $this->dbo->quote($main_pos) . ",
                        pos_name_none = " . $this->dbo->quote($none_pos) . ",
                        pos_name_left = " . $this->dbo->quote($left_pos) . ",
                        pos_name_right = " . $this->dbo->quote($right_pos) . ",
                        pos_name_whole = " . $this->dbo->quote($whole_pos) . ",
                        pos_name_extra = " . $this->dbo->quote($extra_pos) . "
                    WHERE menuitemid = " . $this->item_id[$item];

                    $this->dbo->query($update_item);
                    $this->inserted_item[] = $item;

                }
            }

            if ($topping_item[$name] == 'Custom' || $topping_item[$name] = 'Pizza') {
                if (is_numeric($left) && is_numeric($whole) && is_numeric($right)) {
                    $query =
                        "UPDATE `cs_toppingitems`
                        SET `left_price` = $left,
                            `whole_price`= $whole,
                            `right_price` = $right,
                            `toppingitemprice` = 0.0
                        WHERE `toppingitemname` = '$item'
                                AND `toppinggroupid` = $group_id
                    ";

                    $stmt = $this->dbo->prepare($query);
                    $stmt->execute();
                }
            }

            if ($topping_item[$name] == 'Custom') {
                $labels            = explode(";", $custom_labels);
                $labels_indb_array = $this->labelsForGroupExist($group_id);
                $gate_check_label  = count($labels_indb_array);
                if ($gate_check_label < 3) {
                    foreach ($labels as $label) {
                        $label = trim($label);

                        // if no labels yet for current groups
                        $query =
                        "INSERT INTO `cs_custom_topping_labels`
                            (
                            `topping_group_id`,
                            `topping_label`
                            )
                            VALUES
                                (
                                :groupid,
                            " . $this->dbo->quote($label) . "
                                )
                            ";

                        $stmt = $this->dbo->prepare($query);
                        $stmt->bindParam(':groupid', $group_id);

                        $stmt->execute();
                    }
                } elseif ($gate_check_label == 3) {
                    foreach ($labels as $key => $value) {
                        $label = trim($labels[$key]);
                        echo $labels[$key];
                        $update_label =
                        "UPDATE cs_custom_topping_labels
                            SET topping_label = " . $this->dbo->quote($label) . "
                            WHERE topping_group_id = " . $group_id . "
                            AND custom_topping_label_id = " . $labels_indb_array[$key]['custom_topping_label_id'];
                        $this->dbo->query($update_label);
                    }
                }
            }

        }

    }

    // print_r("<pre>");
    // print_r($this->group_id);
    // print_r("</pre>");
}
