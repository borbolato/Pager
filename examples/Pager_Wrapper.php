<?php
//
// Pager_Wrapper
// -------------
//
// Ready-to-use wrappers for paging the result of a query,
// when fetching the whole resultset is NOT an option.
// This is a performance- and memory-savvy method
// to use PEAR::Pager with a database.
// With this approach, the network load can be
// consistently smaller than with PEAR::DB_Pager.
//
// The following wrappers are provided: one for each PEAR
// db abstraction layer (DB, MDB and MDB2), one for
// PEAR::DB_DataObject, and one for the PHP Eclipse library
//
//
// SAMPLE USAGE
// ------------
//
// $query = 'SELECT this, that FROM mytable';
// require_once 'Pager_Wrapper.php'; //this file
// $pagerOptions = array(
//     'mode'     => 'Sliding',
//     'delta'    => 2,
//     'perPage'  => 15,
// );
// $paged_data = Pager_Wrapper_MDB2($db, $query, $pagerOptions);
// //$paged_data['data'];   //paged data
// //$paged_data['links'];   //xhtml links for page navigation
// //$paged_data['page_numbers'];   //array('current', 'total');
//

/**
 * @param object PEAR::DB instance
 * @param string db query
 * @param array  PEAR::Pager options
 * @param boolean Disable pagination (get all results)
 * @param integer fetch mode constant
 * @param array  parameters for query placeholders
 * @return array with links and paged data
 */
function Pager_Wrapper_DB(&$db, $query, $pager_options = array(), $disabled = false, $fetchMode = DB_FETCHMODE_ASSOC, $dbparams = null)
{
   if (!array_key_exists('totalItems', $pager_options)) {
        //  be smart and try to guess the total number of records
        if (stristr($query, 'GROUP BY') === false) {
            //no GROUP BY => do a fast COUNT(*) on the rewritten query
            $queryCount = 'SELECT COUNT(*)'.stristr($query, ' FROM ');
            list($queryCount, ) = spliti('ORDER BY ', $queryCount);
            list($queryCount, ) = spliti('LIMIT ', $queryCount);
            $totalItems = $db->getOne($queryCount, $dbparams);
            if (DB::isError($totalItems)) {
                return $totalItems;
            }
        } else {
            //GROUP BY => fetch the whole resultset and count the rows returned
            $res =& $db->query($query, $dbparams);
            if (DB::isError($res)) {
                return $res;
            }
            $totalItems = (int)$res->numRows();
            $res->free();
        }
        $pager_options['totalItems'] = $totalItems;
    }
    require_once 'Pager/Pager.php';
    $pager = Pager::factory($pager_options);

    $page = array();
    $page['totalItems'] = $pager_options['totalItems'];
    $page['links'] = $pager->links;
    $page['page_numbers'] = array(
        'current' => $pager->getCurrentPageID(),
        'total'   => $pager->numPages()
    );
    list($page['from'], $page['to']) = $pager->getOffsetByPageId();

    $res = ($disabled)
        ? $db->limitQuery($query, 0, $totalItems, $dbparams)
        : $db->limitQuery($query, $page['from']-1, $pager_options['perPage'], $dbparams);

    if (DB::isError($res)) {
        return $res;
    }
    $page['data'] = array();
    while ($res->fetchInto($row, $fetchMode)) {
       $page['data'][] = $row;
    }
    if ($disabled) {
        $page['links'] = '';
        $page['page_numbers'] = array(
            'current' => 1,
            'total'   => 1
        );
    }
    return $page;
}

/**
 * @param object PEAR::MDB instance
 * @param string db query
 * @param array  PEAR::Pager options
 * @param boolean Disable pagination (get all results)
 * @param integer fetch mode constant
 * @return array with links and paged data
 */
function Pager_Wrapper_MDB(&$db, $query, $pager_options = array(), $disabled = false, $fetchMode = MDB_FETCHMODE_ASSOC)
{
    if (!array_key_exists('totalItems', $pager_options)) {
        //be smart and try to guess the total number of records
        if (stristr($query, 'GROUP BY') === false) {
            //no GROUP BY => do a fast COUNT(*) on the rewritten query
            $queryCount = 'SELECT COUNT(*)'.stristr($query, ' FROM ');
            list($queryCount, ) = spliti('ORDER BY ', $queryCount);
            list($queryCount, ) = spliti('LIMIT ', $queryCount);
            $totalItems = $db->queryOne($queryCount);
            if (MDB::isError($totalItems)) {
                return $totalItems;
            }
        } else {
            //GROUP BY => fetch the whole resultset and count the rows returned
            $res = $db->query($query);
            if (MDB::isError($res)) {
                return $res;
            }
            $totalItems = (int)$db->numRows($res);
            $db->freeResult($res);
        }
        $pager_options['totalItems'] = $totalItems;
    }
    require_once 'Pager/Pager.php';
    $pager = Pager::factory($pager_options);

    $page = array();
    $page['totalItems'] = $pager_options['totalItems'];
    $page['links'] = $pager->links;
    $page['page_numbers'] = array(
        'current' => $pager->getCurrentPageID(),
        'total'   => $pager->numPages()
    );
    list($page['from'], $page['to']) = $pager->getOffsetByPageId();

    $res = ($disabled)
        ? $db->limitQuery($query, null, 0, $totalItems)
        : $db->limitQuery($query, null, $page['from']-1, $pager_options['perPage']);

    if (MDB::isError($res)) {
        return $res;
    }
    $page['data'] = array();
    while ($row = $db->fetchInto($res, $fetchMode)) {
        $page['data'][] = $row;
    }
    if ($disabled) {
        $page['links'] = '';
        $page['page_numbers'] = array(
            'current' => 1,
            'total'   => 1
        );
    }
    return $page;
}

/**
 * @param object PEAR::MDB2 instance
 * @param string db query
 * @param array  PEAR::Pager options
 * @param boolean Disable pagination (get all results)
 * @param integer fetch mode constant
 * @return array with links and paged data
 */
function Pager_Wrapper_MDB2(&$db, $query, $pager_options = array(), $disabled = false, $fetchMode = MDB2_FETCHMODE_ASSOC)
{
    if (!array_key_exists('totalItems', $pager_options)) {
        //be smart and try to guess the total number of records
        if (stristr($query, 'GROUP BY') === false) {
            //no GROUP BY => do a fast COUNT(*) on the rewritten query
            $queryCount = 'SELECT COUNT(*)'.stristr($query, ' FROM ');
            list($queryCount, ) = spliti('ORDER BY ', $queryCount);
            list($queryCount, ) = spliti('LIMIT ', $queryCount);
            $totalItems = $db->queryOne($queryCount);
            if (MDB2::isError($totalItems)) {
                return $totalItems;
            }
        } else {
            //GROUP BY => fetch the whole resultset and count the rows returned
            $res =& $db->query($query);
            if (MDB2::isError($res)) {
                return $res;
            }
            $totalItems = (int)$res->numRows();
            $res->free();
        }
        $pager_options['totalItems'] = $totalItems;
    }
    require_once 'Pager/Pager.php';
    $pager = Pager::factory($pager_options);

    $page = array();
    $page['links'] = $pager->links;
    $page['totalItems'] = $pager_options['totalItems'];
    $page['page_numbers'] = array(
        'current' => $pager->getCurrentPageID(),
        'total'   => $pager->numPages()
    );
    list($page['from'], $page['to']) = $pager->getOffsetByPageId();
    $page['limit'] = $page['to'] - $page['from'] +1;
    if (!$disabled) {
        $db->setLimit($pager_options['perPage'], $page['from']-1);
    }
    $page['data'] = $db->queryAll($query, null, $fetchMode);
    if (MDB2::isError($page['data'])) {
        return $page['data'];
    }
    if ($disabled) {
        $page['links'] = '';
        $page['page_numbers'] = array(
            'current' => 1,
            'total'   => 1
        );
    }
    return $page;
}

/**
 * @param object PEAR::DataObject instance
 * @param array  PEAR::Pager options
 * @param boolean Disable pagination (get all results)
 * @return array with links and paged data
 * @author Massimiliano Arione <garak@studenti.it>
 */
function Pager_Wrapper_DBDO(&$db, $pager_options = array(), $disabled = false)
{
    if (!array_key_exists('totalItems', $pager_options)) {
        // be smart and try to guess the total number of records
        $totalItems = $db->count();
        $pager_options['totalItems'] = $totalItems;
    }
    require_once 'Pager/Pager.php';
    $pager = Pager::factory($pager_options);

    $page = array();
    $page['links'] = $pager->links;
    $page['page_numbers'] = array(
        'current' => $pager->getCurrentPageID(),
        'total'   => $pager->numPages()
    );
    list($page['from'], $page['to']) = $pager->getOffsetByPageId();
    $page['limit'] = $page['to'] - $page['from'] + 1;
    if (!$disabled) {
        $db->limit($page['from'] - 1, $pager_options['perPage']);
    }
    $db->find();
    while ($db->fetch()) {
        $page['data'][] = $db->toArray();
    }
    return $page;
}

/**
 * @param object PHP Eclipse instance
 * @param string db query
 * @param array  PEAR::Pager options
 * @param boolean Disable pagination (get all results)
 * @return array with links and paged data
 * @author Matte Edens <matte@arubanetworks.com>
 * @see http://sourceforge.net/projects/eclipselib/
 */
function Pager_Wrapper_Eclipse(&$db, $query, $pager_options = array(), $disabled = false)
{
    if (!$disabled) {
        require_once(ECLIPSE_ROOT . 'PagedQuery.php');
        $query =& new PagedQuery($db->query($query), $pager_options['perPage']);
        $totalrows = $query->getRowCount();
        $numpages = $query->getPageCount();
        $whichpage = isset($_GET[$pager_options['urlVar']]) ? (int)$_GET[$pager_options['urlVar']] - 1 : 0;
        if ($whichpage >= $numpages) {
            $whichpage = $numpages - 1;
        }
        $result = $query->getPage($whichpage);
    } else {
        $result = $db->query($query);
        $totalrows = $result->getRowCount();
        $numpages = 1;
    }
    if (!$result->isSuccess()) {
        return PEAR::raiseError($result->getErrorMessage());
    }
    if (!array_key_exists('totalItems', $pager_options)) {
        $pager_options['totalItems'] = $totalrows;
    }

    $page = array();
    require_once(ECLIPSE_ROOT . 'QueryIterator.php');
    for ($it =& new QueryIterator($result); $it->isValid(); $it->next()) {
        $page['data'][] =& $it->getCurrent();
    }
    require_once 'Pager/Pager.php';
    $pager = Pager::factory($pager_options);

    $page['links'] = $pager->links;
    $page['totalItems'] = $pager_options['totalItems'];
    $page['page_numbers'] = array(
        'current' => $pager->getCurrentPageID(),
        'total'   => $numpages
    );
	$page['perPageSelectBox'] = $pager->getperpageselectbox();
    list($page['from'], $page['to']) = $pager->getOffsetByPageId();
    $page['limit'] = $page['to'] - $page['from'] +1;
    if ($disabled) {
        $page['links'] = '';
        $page['page_numbers'] = array(
            'current' => 1,
            'total'   => 1
        );
    }
    return $page;
}
?>