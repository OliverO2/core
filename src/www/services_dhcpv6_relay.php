<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2003-2004 Justin Ellison <justin@techadvise.com>.
    Copyright (C) 2010  Ermal Luçi
    Copyright (C) 2010  Seth Mos
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("services.inc");


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig['enable'] = isset($config['dhcrelay6']['enable']);
    if (empty($config['dhcrelay6']['interface'])) {
        $pconfig['interface'] = array();
    } else {
        $pconfig['interface'] = explode(",", $config['dhcrelay6']['interface']);
    }
    if (empty($config['dhcrelay6']['server'])) {
        $pconfig['server'] = "";
    } else {
        $pconfig['server'] = $config['dhcrelay6']['server'];
    }
    $pconfig['agentoption'] = isset($config['dhcrelay6']['agentoption']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "server interface");
    $reqdfieldsn = array(gettext("Destination Server"), gettext("Interface"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['server'])) {
        $checksrv = explode(",", $pconfig['server']);
        foreach ($checksrv as $srv) {
            if (!is_ipaddrv6($srv)) {
                $input_errors[] = gettext("A valid Destination Server IPv6 address  must be specified.");
            }
        }
    }

    if (count($input_errors) == 0) {
        $config['dhcrelay6']['enable'] = !empty($pconfig['enable']);
        $config['dhcrelay6']['interface'] = implode(",", $pconfig['interface']);
        $config['dhcrelay6']['agentoption'] = !empty($pconfig['agentoption']);
        $config['dhcrelay6']['server'] = $pconfig['server'];
        write_config();
        // reconfigure
        services_dhcrelay6_configure();
        header("Location: services_dhcpv6_relay.php");
        exit;
    }
}

/*   set the enabled flag which will tell us if DHCP server is enabled
 *   on any interface.   We will use this to disable dhcp-relay since
 *   the two are not compatible with each other.
 */
$dhcpd_enabled = false;
if (is_array($config['dhcpdv6'])) {
    foreach($config['dhcpdv6'] as $dhcp) {
        if (isset($dhcp['enable'])) {
            $dhcpd_enabled = true;
        }
    }
}

$service_hook = 'dhcrelay6';

include("head.inc");

?>

<body>


<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <?php if ($dhcpd_enabled): ?>
              <p><?= gettext('DHCPv6 Server is currently enabled.  Cannot enable the DHCPv6 Relay service while the DHCPv6 Server is enabled on any interface.') ?></p>
              <?php else: ?>
              <header class="content-box-head container-fluid">
                  <h3><?=gettext("DHCPv6 Relay configuration"); ?></h3>
              </header>
              <div class="content-box-main ">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <tr>
                      <td width="22%"><i class="fa fa-info-circle text-muted"></i> <?= gettext('Enable') ?></td>
                      <td width="78%">
                        <input name="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?>/>
                        <strong><?=gettext("Enable DHCPv6 relay on interface");?></strong>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Interface(s)') ?></td>
                      <td>
                        <select name="interface[]" multiple="multiple" class="selectpicker">
<?php
                        $iflist = get_configured_interface_with_descr();
                        foreach ($iflist as $ifent => $ifdesc):
                            if (!is_ipaddrv6(get_interface_ipv6($ifent))) {
                                continue;
                            }?>

                          <option value="<?=$ifent;?>" <?=!empty($pconfig['interface']) && in_array($ifent, $pconfig['interface']) ? " selected=\"selected\"" : "";?> >
                            <?=$ifdesc;?>
                          </option>
<?php
                        endforeach;?>
                        </select>
                        <div class="hidden" for="help_for_interface">
                          <?=gettext("Interfaces without an IPv6 address will not be shown."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_agentoption" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Append circuit ID");?></td>
                      <td>
                        <input name="agentoption" type="checkbox" value="yes" <?=!empty($pconfig['agentoption']) ? "checked=\"checked\"" : ""; ?> />
                        <div class="hidden" for="help_for_agentoption">
                          <?php printf(gettext("If this is checked, the DHCPv6 relay will append the circuit ID (%s interface number) and the agent ID to the DHCPv6 request."), $g['product_name']); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination server");?></td>
                      <td>
                        <input name="server" type="text" value="<?=!empty($pconfig['server']) ? htmlspecialchars($pconfig['server']):"";?>" />
                        <div class="hidden" for="help_for_server">
                          <?=gettext("This is the IPv6 address of the server to which DHCPv6 requests are relayed. You can enter multiple server IPv6 addresses, separated by commas. ");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td></td>
                      <td>
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      </td>
                    </tr>
                  </table>
                </div>
              </div>
<?php
              endif; ?>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
