#! /usr/bin/env python3
"""
 wrapper        A small tool which wraps services, discovery and poller php scripts
                in order to run them as threads with Queue and workers

 Authors:       Orsiris de Jong <contact@netpower.fr>
                Neil Lathwood <neil@librenms.org>
                Job Snijders <job.snijders@atrato.com>

                Distributed poller code (c) 2015, GPLv3, Daniel Preussker <f0o@devilcode.org>
                All code parts that belong to Daniel are enclosed in EOC comments

 Date:          Jul 2021

 Usage:         This program accepts three command line arguments
                - the number of threads (defaults to 1 for discovery / service, and 16 for poller)
                - the wrapper type (services-wrapper, discovery-wrapper or poller-wrapper)
                - optional debug boolean


 Ubuntu Linux:  apt-get install python-mysqldb
 FreeBSD:       cd /usr/ports/*/py-MySQLdb && make install clean
 RHEL 7:        yum install MySQL-python
 RHEL 8:        dnf install mariadb-connector-c-devel gcc && python -m pip install mysqlclient

 Tested on:     Python 3.6.8 / PHP 7.2.11 / CentOS 8 / AlmaLinux 8.4

 License:       This program is free software: you can redistribute it and/or modify it
                under the terms of the GNU General Public License as published by the
                Free Software Foundation, either version 3 of the License, or (at your
                option) any later version.

                This program is distributed in the hope that it will be useful, but
                WITHOUT ANY WARRANTY; without even the implied warranty of
                MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
                Public License for more details.

                You should have received a copy of the GNU General Public License along
                with this program. If not, see https://www.gnu.org/licenses/.

                LICENSE.txt contains a copy of the full GPLv3 licensing conditions.
"""

import logging
import json
import os
import queue
import sys
import threading
import time
import uuid
from argparse import ArgumentParser

import LibreNMS.library as lnms
from LibreNMS.command_runner import command_runner

logger = logging.getLogger(__name__)

PER_DEVICE_TIMEOUT = 900  # Timeout in seconds for any poller / service / discovery action per device

DISTRIBUTED_POLLING = False  # Is overriden by config.php
REAL_DURATION = 0
DISCOVERED_DEVICES_COUNT = 0
PER_DEVICE_DURATION = {}

MEMC = None
IS_NODE = None
STEPPING = None
MASTER_TAG = None
NODES_TAG = None
TIME_TAG = ''

wrappers = {
    """
    Per wrapper type configuration
    All time related variables are in seconds
    """
    'service': {
        'executable': 'check-services.php',
        'table_name': 'services',
        'memc_touch_time': 10,
        'stepping': 300,
        'nodes_stepping': 300,
        'total_exec_time': 300,
    },
    'discovery': {
        'executable': 'discovery.php',
        'table_name': 'devices',
        'memc_touch_time': 30,
        'stepping': 300,
        'nodes_stepping': 3600,
        'total_exec_time': 21600
    },
    'poller': {
        'executable': 'poller.php',
        'table_name': 'devices',
        'memc_touch_time': 10,
        'stepping': 300,
        'nodes_stepping': 300,
        'total_exec_time': 300,
    }
}

"""
 Threading helper functions
"""


#  <<<EOC
def memc_alive(name  # Type: str
               ):
    """
    Checks if memcache is working by injecting a random string and trying to read it again
    """
    try:
        key = str(uuid.uuid4())
        MEMC.set(name + ".ping." + key, key, 60)
        if MEMC.get(name + ".ping." + key) == key:
            MEMC.delete(name + ".ping." + key)
            return True
        return False
    except:
        return False


def memc_touch(key,  # Type: str
               _time  # Type: int
               ):
    """
    Updates a memcache key wait time
    """
    try:
        val = MEMC.get(key)
        MEMC.set(key, val, _time)
    except:
        pass


def get_time_tag(step  # Type: int
                 ):
    """
    Get current time tag as timestamp module stepping
    """
    timestamp = int(time.time())
    return timestamp - timestamp % step


# EOC


def print_worker(print_queue,  # Type: Queue
                 wrapper_type  # Type: str
                 ):
    """
        A seperate queue and a single worker for printing information to the screen prevents
        the good old joke:

            Some people, when confronted with a problem, think,
            "I know, I'll use threads," and then they have two problems.
    """
    nodeso = 0
    while True:
        #  <<<EOC
        global IS_NODE
        global DISTRIBUTED_POLLING
        if DISTRIBUTED_POLLING:
            if not IS_NODE:
                memc_touch(MASTER_TAG, wrappers[wrapper_type]['memc_touch_time'])
                nodes = MEMC.get(NODES_TAG)
                if nodes is None and not memc_alive(wrapper_type):
                    logger.warning(
                        "Lost Memcached. Taking over all devices. Nodes will quit shortly."
                    )
                    DISTRIBUTED_POLLING = False
                    nodes = nodeso
                if nodes is not nodeso:
                    logger.info("{} Node(s) Total".format(nodes))
                    nodeso = nodes
            else:
                memc_touch(NODES_TAG, wrappers[wrapper_type]['memc_touch_time'])
            try:
                worker_id, device_id, elapsed_time = print_queue.get(False)
            except:
                pass
                try:
                    time.sleep(1)
                except:
                    pass
                continue
        else:
            worker_id, device_id, elapsed_time = print_queue.get()
        # EOC

        global REAL_DURATION
        global PER_DEVICE_DURATION
        global DISCOVERED_DEVICES_COUNT

        REAL_DURATION += elapsed_time
        PER_DEVICE_DURATION[device_id] = elapsed_time
        DISCOVERED_DEVICES_COUNT += 1
        if elapsed_time < STEPPING:
            logger.info(
                "worker {} finished device {} in {} seconds".format(worker_id, device_id, elapsed_time)
            )
        else:
            logger.warning(
                "worker {} finished device {} in {} seconds".format(worker_id, device_id, elapsed_time)
                % (worker_id, device_id, elapsed_time)
            )
        print_queue.task_done()


def poll_worker(poll_queue,  # Type: Queue
                print_queue,  # Type: Queue
                config,  # Type: dict
                log_dir,  # Type: str
                wrapper_type  # Type: str
                ):
    """
        This function will fork off single instances of the php process, record
        how long it takes, and push the resulting reports to the printer queue
    """

    while True:
        device_id = poll_queue.get()
        #  <<<EOC
        if not DISTRIBUTED_POLLING or \
            MEMC.get('{}.device.{}{}'.format(wrapper_type, device_id, TIME_TAG)) is None:
            if DISTRIBUTED_POLLING:
                result = MEMC.add(
                    '{}.device.{}{}'.format(wrapper_type, device_id, TIME_TAG),
                    config["distributed_poller_name"],
                    STEPPING,
                )
                if not result:
                    logger.info(
                        "The device {} appears to be being checked by another node".format(device_id)
                    )
                    poll_queue.task_done()
                    continue
                if not memc_alive(wrapper_type) and IS_NODE:
                    logger.warning(
                        "Lost Memcached, Not checking Device {} as Node. Master will check it.".format(
                            device_id)
                    )
                    poll_queue.task_done()
                    continue
            # EOC
            try:
                start_time = time.time()

                device_log = os.path.join(log_dir, 'services_device_{}.log'.format(device_id))
                command = '/usr/bin/env php {} -h {} {}'.format(wrappers[wrapper_type]['executable'],
                                                                device_id,
                                                                device_log)
                exit_code, output = command_runner(command, shell=True, timeout=PER_DEVICE_TIMEOUT)
                # logger.debug(output, exit_code)  # TODO Check why this may fail with
                # TypeError: not all arguments converted during string formatting
                elapsed_time = int(time.time() - start_time)
                print_queue.put(
                    [threading.current_thread().name, device_id, elapsed_time]
                )
            except (KeyboardInterrupt, SystemExit):
                raise
            except:
                pass
        poll_queue.task_done()


def wrapper(wrapper_type,  # Type: str
            amount_of_workers,  # Type: int
            config,  # Type: dict
            log_dir,  # Type: str
            _debug=False,  # Type: bool
            ):  # -> None
    """
    Actual code that runs various php scripts, in single node mode or distributed poller mode
    """

    global MEMC
    global IS_NODE
    global DISTRIBUTED_POLLING
    global MASTER_TAG
    global NODES_TAG
    global TIME_TAG
    global STEPPING

    # Setup wrapper dependent variables
    STEPPING = wrappers[wrapper_type]['stepping']
    if wrapper_type == 'poller':
        if "rrd" in config and "step" in config["rrd"]:
            STEPPING = config["rrd"]["step"]
        TIME_TAG = '.' + str(get_time_tag(STEPPING))

    MASTER_TAG = "{}.master{}".format(wrapper_type, TIME_TAG)
    NODES_TAG = "{}.nodes{}".format(wrapper_type, TIME_TAG)

    #  <<<EOC
    if "distributed_poller_group" in config:
        poller_group = str(config["distributed_poller_group"])
    else:
        poller_group = False

    if (
        "distributed_poller" in config
        and "distributed_poller_memcached_host" in config
        and "distributed_poller_memcached_port" in config
        and config["distributed_poller"]
    ):
        try:
            import memcache

            MEMC = memcache.Client(
                [
                    config["distributed_poller_memcached_host"]
                    + ":"
                    + str(config["distributed_poller_memcached_port"])
                ]
            )
            if str(MEMC.get(MASTER_TAG)) == config["distributed_poller_name"]:
                logger.info("This system is already joined as the service master.")
                sys.exit(2)
            if memc_alive(wrapper_type):
                if MEMC.get(MASTER_TAG) is None:
                    logger.info("Registered as Master")
                    MEMC.set(MASTER_TAG, config["distributed_poller_name"], 10)
                    MEMC.set(NODES_TAG, 0, wrappers[wrapper_type]['nodes_stepping'])
                    IS_NODE = False
                else:
                    logger.info("Registered as Node joining Master {}".format(MEMC.get(MASTER_TAG)))
                    IS_NODE = True
                    MEMC.incr(NODES_TAG)
                DISTRIBUTED_POLLING = True
            else:
                logger.warning(
                    "Could not connect to memcached, disabling distributed service checks."
                )
                DISTRIBUTED_POLLING = False
                IS_NODE = False
        except SystemExit:
            raise
        except ImportError:
            logger.critical("ERROR: missing memcache python module:")
            logger.critical("On deb systems: apt-get install python3-memcache")
            logger.critical("On other systems: pip3 install python-memcached")
            logger.critical("Disabling distributed discovery.")
            DISTRIBUTED_POLLING = False
    else:
        DISTRIBUTED_POLLING = False
    # EOC

    s_time = time.time()

    devices_list = []

    if wrapper_type == 'services':
        #  <<<EOC
        if poller_group is not False:
            query = (
                "SELECT DISTINCT(`services`.`device_id`) FROM `services` LEFT JOIN `devices` ON "
                "`services`.`device_id` = `devices`.`device_id` WHERE `devices`.`poller_group` IN({}) AND "
                "`devices`.`disabled` = 0".format(poller_group)
            )
        else:
            query = "SELECT DISTINCT(`services`.`device_id`) FROM `services` LEFT JOIN `devices` ON " \
                    "`services`.`device_id` = `devices`.`device_id` WHERE `devices`.`disabled` = 0"
        # EOC
    elif wrapper_type in ['discovery', 'poller']:
        """
            This query specificly orders the results depending on the last_discovered_timetaken variable
            Because this way, we put the devices likely to be slow, in the top of the queue
            thus greatening our chances of completing _all_ the work in exactly the time it takes to
            discover the slowest device! cool stuff he
        """
        #  <<<EOC
        if poller_group is not False:
            query = (
                "SELECT `device_id` FROM `devices` WHERE `poller_group` IN ({}) AND "
                "`disabled` = 0 ORDER BY `last_polled_timetaken` DESC".format(poller_group)
            )
        else:
            query = "select device_id from devices where disabled = 0 order by last_polled_timetaken desc"
        # EOC
    else:
        sys.exit(3)

    db_connection = lnms.db_open(
        config["db_socket"],
        config["db_host"],
        int(config["db_port"]),
        config["db_user"],
        config["db_pass"],
        config["db_name"],
    )
    cursor = db_connection.cursor()
    cursor.execute(query)
    devices = cursor.fetchall()
    for row in devices:
        devices_list.append(int(row[0]))
    #  <<<EOC
    if DISTRIBUTED_POLLING and not IS_NODE:
        query = "SELECT max(device_id),min(device_id) FROM `{}`".format(wrappers[wrapper_type]['table_name'])
        cursor.execute(query)
        devices = cursor.fetchall()
        maxlocks = devices[0][0] or 0
        minlocks = devices[0][1] or 0
    # EOC

    poll_queue = queue.Queue()
    print_queue = queue.Queue()

    logger.info(
        "starting the {} check at {} with {} threads for {} devices".format(wrapper_type,
                                                                            time.strftime("%Y-%m-%d %H:%M:%S"),
                                                                            amount_of_workers,
                                                                            len(devices_list))
    )

    for device_id in devices_list:
        poll_queue.put(device_id)

    for _ in range(amount_of_workers):
        worker = threading.Thread(target=poll_worker,
                                  kwargs={'poll_queue': poll_queue, 'print_queue': print_queue, 'config': config,
                                          'log_dir': log_dir, 'wrapper_type': wrapper_type})
        worker.setDaemon(True)
        worker.start()

    pworker = threading.Thread(target=print_worker, kwargs={'print_queue': print_queue, 'wrapper_type': wrapper_type})
    pworker.setDaemon(True)
    pworker.start()

    try:
        poll_queue.join()
        print_queue.join()
    except (KeyboardInterrupt, SystemExit):
        raise

    total_time = int(time.time() - s_time)

    logger.info(
        "{}-wrapper checked {} devices in {} seconds with {} workers".format(wrapper_type,
                                                                             DISCOVERED_DEVICES_COUNT,
                                                                             total_time,
                                                                             amount_of_workers)
    )

    #  <<<EOC
    if DISTRIBUTED_POLLING or memc_alive(wrapper_type):
        master = MEMC.get(MASTER_TAG)
        if master == config["distributed_poller_name"] and not IS_NODE:
            logger.info("Wait for all service-nodes to finish")
            nodes = MEMC.get(NODES_TAG)
            while nodes is not None and nodes > 0:
                try:
                    time.sleep(1)
                    nodes = MEMC.get(NODES_TAG)
                except:
                    pass
            logger.info("Clearing Locks for {}".format(NODES_TAG))
            x = minlocks
            while x <= maxlocks:
                MEMC.delete("{}.device.{}".format(wrapper_type, x))
                x = x + 1
            logger.info("{} Locks Cleared".format(x))
            logger.info("Clearing Nodes")
            MEMC.delete(MASTER_TAG)
            MEMC.delete(NODES_TAG)
        else:
            MEMC.decr(NODES_TAG)
        logger.info("Finished {}.".format(time.time()))
    # EOC

    show_stopper = False

    # Update poller statistics
    if wrapper_type == 'poller':
        cursor = db_connection.cursor()
        query = (
            "UPDATE pollers SET last_polled=NOW(), devices='{}', time_taken='{}' WHERE poller_name='{}'".format(
                DISCOVERED_DEVICES_COUNT, total_time, config["distributed_poller_name"])
        )
        response = cursor.execute(query)
        if response == 1:
            db_connection.commit()
        else:
            query = (
                "INSERT INTO pollers SET poller_name='{}', last_polled=NOW(), devices='{}', time_taken='{}'".format(
                    config["distributed_poller_name"], DISCOVERED_DEVICES_COUNT, total_time)
            )
            cursor.execute(query)
            db_connection.commit()

    db_connection.close()

    if total_time > wrappers[wrapper_type]['total_exec_time']:
        logger.warning(
            "the process took more than {} seconds to finish, you need faster hardware or more threads".format(
                wrappers[wrapper_type]['total_exec_time'])
        )
        logger.warning(
            "in sequential style service checks the elapsed time would have been: {} seconds".format(REAL_DURATION)
        )
        for device in PER_DEVICE_DURATION:
            if PER_DEVICE_DURATION[device] > wrappers[wrapper_type]['nodes_stepping']:
                logger.warning(
                    "device {} is taking too long: {} seconds".format(device, PER_DEVICE_DURATION[device])
                )
                show_stopper = True
        if show_stopper:
            logger.error(
                "Some devices are taking more than {} seconds, the script cannot recommend you what to do.".format(
                    wrappers[wrapper_type]['nodes_stepping'])
            )
        else:
            recommend = int(total_time / STEPPING * amount_of_workers + 1)
            logger.warning(
                "Consider setting a minimum of {} threads. (This does not constitute professional advice!)".format(
                    recommend)
            )

        sys.exit(2)


def get_config(install_dir  #  Type: str
               ):  # -> dict
    """
    Gets current LibreNMS configfuration
    """

    lnms.check_for_file(os.path.join(install_dir, ".env"))
    return json.loads(lnms.get_config_data(install_dir))


if __name__ == '__main__':
    parser = ArgumentParser(prog='wrapper.py',
                            usage="usage: %(prog)s [options] <wrapper_type> <workers>\n"
                                  "wrapper_type = 'service', 'poller' or 'disccovery'"
                                  "workers defaults to 1 for service and discovery, and 16 for poller "
                                  "(Do not set too high, or you will get an OOM)",
                            description="Spawn multiple librenms php processes in parallel.")
    parser.add_argument(
        "-d",
        "--debug",
        action="store_true",
        default=False,
        help="Enable debug output. WARNING: Leaving this enabled will consume a lot of disk space.",
    )

    parser.add_argument(
        dest="wrapper",
        default=None,
        help="Execute wrapper for 'service', 'poller' or 'discovery'"
    )
    parser.add_argument(
        dest="threads",
        action="store_true",
        default=None,
        help="Number of workers"
    )



    args = parser.parse_args()

    debug = args.debug
    wrapper_type = args.wrapper
    amount_of_workers = args.threads

    if wrapper_type not in ['service', 'discovery', 'poller']:
        parser.error("Invalid wrapper type '{}'".format(wrapper_type))
        sys.exit(4)

    config = get_config()
    log_dir = config["log_dir"]
    log_file = os.path.join(log_dir, wrapper_type + ".log")
    logger = lnms.logger_get_logger(log_file, debug=debug)

    try:
        amount_of_workers = int(amount_of_workers)
    except (IndexError, ValueError, TypeError):
        amount_of_workers = 16 if wrapper_type == 'poller' else 1  # Defaults to 1 for service/discovery, 16 for poller
        logger.warning("Bogus number of workers given. Using default number ({}) of workers.".format(amount_of_workers))

    wrapper(wrapper_type, amount_of_workers, config, log_dir, _debug=debug)