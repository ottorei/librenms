import logging
import os
import pymysql  # pylint: disable=import-error
import sys
import threading
import time

import LibreNMS
from LibreNMS.config import DBConfig

try:
    import psutil
except ImportError:
    pass

from datetime import timedelta
from datetime import datetime
from platform import python_version
from time import sleep
from socket import gethostname
from signal import signal, SIGTERM, SIGQUIT, SIGINT, SIGHUP, SIGCHLD, SIG_IGN
from uuid import uuid1
from os import utime

try:
    from systemd.daemon import notify
except ImportError:
    pass

try:
    from redis.exceptions import ConnectionError as RedisConnectionError
except ImportError:

    class RedisConnectionError(Exception):
        pass


logger = logging.getLogger(__name__)


class ServiceConfig(DBConfig):
    def __init__(self):
        """
        Stores all of the configuration variables for the LibreNMS service in a common object
        Starts with defaults, but can be populated with variables from config.php by calling populate()
        """
        self._uuid = str(uuid1())
        self.set_name(gethostname())

    def set_name(self, name):
        if name:
            self.name = name.strip()
            self.unique_name = "{}-{}".format(self.name, self._uuid)

    class PollerConfig:
        def __init__(self, workers, frequency, calculate=None):
            self.enabled = True
            self.workers = workers
            self.frequency = frequency
            self.calculate = calculate

    # config variables with defaults
    BASE_DIR = os.path.abspath(
        os.path.join(os.path.dirname(os.path.realpath(__file__)), os.pardir)
    )

    node_id = None
    name = None
    unique_name = None
    single_instance = True
    distributed = False
    group = 0

    debug = False
    log_level = 20
    max_db_failures = 5

    alerting = PollerConfig(1, 60)
    poller = PollerConfig(24, 300)
    services = PollerConfig(8, 300)
    discovery = PollerConfig(16, 21600)
    billing = PollerConfig(2, 300, 60)
    ping = PollerConfig(1, 60)
    down_retry = 60
    update_enabled = True
    update_frequency = 86400

    master_resolution = 1
    master_timeout = 10

    redis_host = "localhost"
    redis_port = 6379
    redis_db = 0
    redis_user = None
    redis_pass = None
    redis_socket = None
    redis_sentinel = None
    redis_sentinel_user = None
    redis_sentinel_pass = None
    redis_sentinel_service = None
    redis_timeout = 60

    log_output = False
    logdir = "logs"

    watchdog_enabled = False
    watchdog_logfile = "logs/librenms.log"
    health_file = ""  # disabled by default

    def populate(self):
        config = LibreNMS.get_config_data(self.BASE_DIR)

        # populate config variables
        self.node_id = os.getenv("NODE_ID")
        self.set_name(config.get("distributed_poller_name", None))
        self.distributed = config.get("distributed_poller", ServiceConfig.distributed)
        self.group = ServiceConfig.parse_group(
            config.get("distributed_poller_group", ServiceConfig.group)
        )

        # backward compatible options
        self.master_timeout = config.get(
            "service_master_timeout", ServiceConfig.master_timeout
        )
        self.poller.workers = config.get(
            "poller_service_workers", ServiceConfig.poller.workers
        )
        self.poller.frequency = config.get(
            "poller_service_poll_frequency", ServiceConfig.poller.frequency
        )
        self.discovery.frequency = config.get(
            "poller_service_discover_frequency", ServiceConfig.discovery.frequency
        )
        self.down_retry = config.get(
            "poller_service_down_retry", ServiceConfig.down_retry
        )
        self.log_level = config.get("poller_service_loglevel", ServiceConfig.log_level)

        # new options
        self.poller.enabled = (
            config.get("service_poller_enabled", True)
            if config.get("schedule_type").get("poller", "legacy") == "legacy"
            else config.get("schedule_type").get("poller", "legacy") == "dispatcher"
        )
        self.poller.workers = config.get(
            "service_poller_workers", ServiceConfig.poller.workers
        )
        self.poller.frequency = config.get(
            "service_poller_frequency", ServiceConfig.poller.frequency
        )
        self.discovery.enabled = (
            config.get("service_discovery_enabled", True)
            if config.get("schedule_type").get("discovery", "legacy") == "legacy"
            else config.get("schedule_type").get("discovery", "legacy") == "dispatcher"
        )
        self.discovery.workers = config.get(
            "service_discovery_workers", ServiceConfig.discovery.workers
        )
        self.discovery.frequency = config.get(
            "service_discovery_frequency", ServiceConfig.discovery.frequency
        )
        self.services.enabled = (
            config.get("service_services_enabled", True)
            if config.get("schedule_type").get("services", "legacy") == "legacy"
            else config.get("schedule_type").get("services", "legacy") == "dispatcher"
        )
        self.services.workers = config.get(
            "service_services_workers", ServiceConfig.services.workers
        )
        self.services.frequency = config.get(
            "service_services_frequency", ServiceConfig.services.frequency
        )
        self.billing.enabled = (
            config.get("service_billing_enabled", True)
            if config.get("schedule_type").get("billing", "legacy") == "legacy"
            else config.get("schedule_type").get("billing", "legacy") == "dispatcher"
        )
        self.billing.frequency = config.get(
            "service_billing_frequency", ServiceConfig.billing.frequency
        )
        self.billing.calculate = config.get(
            "service_billing_calculate_frequency", ServiceConfig.billing.calculate
        )
        self.alerting.enabled = (
            config.get("service_alerting_enabled", True)
            if config.get("schedule_type").get("alerting", "legacy") == "legacy"
            else config.get("schedule_type").get("alerting", "legacy") == "dispatcher"
        )
        self.alerting.frequency = config.get(
            "service_alerting_frequency", ServiceConfig.alerting.frequency
        )
        self.ping.enabled = (
            config.get("service_ping_enabled", False)
            if config.get("schedule_type").get("ping", "legacy") == "legacy"
            else config.get("schedule_type").get("ping", "legacy") == "dispatcher"
        )
        self.ping.frequency = config.get("ping_rrd_step", ServiceConfig.ping.frequency)
        self.down_retry = config.get(
            "service_poller_down_retry", ServiceConfig.down_retry
        )
        self.log_level = config.get("service_loglevel", ServiceConfig.log_level)
        self.update_enabled = config.get(
            "service_update_enabled", ServiceConfig.update_enabled
        )
        self.update_frequency = config.get(
            "service_update_frequency", ServiceConfig.update_frequency
        )

        self.redis_host = os.getenv(
            "REDIS_HOST", config.get("redis_host", ServiceConfig.redis_host)
        )
        self.redis_db = os.getenv(
            "REDIS_DB", config.get("redis_db", ServiceConfig.redis_db)
        )
        self.redis_user = os.getenv(
            "REDIS_USERNAME", config.get("redis_user", ServiceConfig.redis_user)
        )
        self.redis_pass = os.getenv(
            "REDIS_PASSWORD", config.get("redis_pass", ServiceConfig.redis_pass)
        )
        self.redis_port = int(
            os.getenv("REDIS_PORT", config.get("redis_port", ServiceConfig.redis_port))
        )
        self.redis_socket = os.getenv(
            "REDIS_SOCKET", config.get("redis_socket", ServiceConfig.redis_socket)
        )
        self.redis_sentinel = os.getenv(
            "REDIS_SENTINEL", config.get("redis_sentinel", ServiceConfig.redis_sentinel)
        )
        self.redis_sentinel_user = os.getenv(
            "REDIS_SENTINEL_USERNAME",
            config.get("redis_sentinel_user", ServiceConfig.redis_sentinel_user),
        )
        self.redis_sentinel_pass = os.getenv(
            "REDIS_SENTINEL_PASSWORD",
            config.get("redis_sentinel_pass", ServiceConfig.redis_sentinel_pass),
        )
        self.redis_sentinel_service = os.getenv(
            "REDIS_SENTINEL_SERVICE",
            config.get("redis_sentinel_service", ServiceConfig.redis_sentinel_service),
        )
        self.redis_timeout = int(
            os.getenv(
                "REDIS_TIMEOUT",
                self.alerting.frequency
                if self.alerting.frequency != 0
                else self.redis_timeout,
            )
        )

        self.db_host = os.getenv(
            "DB_HOST", config.get("db_host", ServiceConfig.db_host)
        )
        self.db_name = os.getenv(
            "DB_DATABASE", config.get("db_name", ServiceConfig.db_name)
        )
        self.db_pass = os.getenv(
            "DB_PASSWORD", config.get("db_pass", ServiceConfig.db_pass)
        )
        self.db_port = int(
            os.getenv("DB_PORT", config.get("db_port", ServiceConfig.db_port))
        )
        self.db_socket = os.getenv(
            "DB_SOCKET", config.get("db_socket", ServiceConfig.db_socket)
        )
        self.db_user = os.getenv(
            "DB_USERNAME", config.get("db_user", ServiceConfig.db_user)
        )
        self.db_sslmode = os.getenv(
            "DB_SSLMODE", config.get("db_sslmode", ServiceConfig.db_sslmode)
        )
        self.db_ssl_ca = os.getenv(
            "MYSQL_ATTR_SSL_CA", config.get("db_ssl_ca", ServiceConfig.db_ssl_ca)
        )

        self.watchdog_enabled = config.get(
            "service_watchdog_enabled", ServiceConfig.watchdog_enabled
        )
        self.logdir = config.get("log_dir", ServiceConfig.BASE_DIR + "/logs")
        self.watchdog_logfile = config.get("log_file", self.logdir + "/librenms.log")
        self.health_file = config.get("service_health_file", ServiceConfig.health_file)

        # set convenient debug variable
        self.debug = logging.getLogger().isEnabledFor(logging.DEBUG)

        if not self.debug and self.log_level:
            try:
                logging.getLogger().setLevel(self.log_level)
            except ValueError:
                logger.error(
                    "Unknown log level {}, must be one of 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'".format(
                        self.log_level
                    )
                )
                logging.getLogger().setLevel(logging.INFO)

    def load_poller_config(self, db):
        try:
            settings = {}
            cursor = db.query(
                "SELECT * FROM `poller_cluster` WHERE `node_id`=%s", self.node_id
            )
            if cursor.rowcount == 0:
                return

            for index, setting in enumerate(cursor.fetchone()):
                name = cursor.description[index][0]
                settings[name] = setting

            if settings["poller_name"] is not None:
                self.set_name(settings["poller_name"])
            if settings["poller_groups"] is not None:
                self.group = ServiceConfig.parse_group(settings["poller_groups"])
            if settings["poller_enabled"] is not None:
                self.poller.enabled = settings["poller_enabled"]
            if settings["poller_frequency"] is not None:
                self.poller.frequency = settings["poller_frequency"]
            if settings["poller_workers"] is not None:
                self.poller.workers = settings["poller_workers"]
            if settings["poller_down_retry"] is not None:
                self.down_retry = settings["poller_down_retry"]
            if settings["discovery_enabled"] is not None:
                self.discovery.enabled = settings["discovery_enabled"]
            if settings["discovery_frequency"] is not None:
                self.discovery.frequency = settings["discovery_frequency"]
            if settings["discovery_workers"] is not None:
                self.discovery.workers = settings["discovery_workers"]
            if settings["services_enabled"] is not None:
                self.services.enabled = settings["services_enabled"]
            if settings["services_frequency"] is not None:
                self.services.frequency = settings["services_frequency"]
            if settings["services_workers"] is not None:
                self.services.workers = settings["services_workers"]
            if settings["billing_enabled"] is not None:
                self.billing.enabled = settings["billing_enabled"]
            if settings["billing_frequency"] is not None:
                self.billing.frequency = settings["billing_frequency"]
            if settings["billing_calculate_frequency"] is not None:
                self.billing.calculate = settings["billing_calculate_frequency"]
            if settings["alerting_enabled"] is not None:
                self.alerting.enabled = settings["alerting_enabled"]
            if settings["alerting_frequency"] is not None:
                self.alerting.frequency = settings["alerting_frequency"]
            if settings["ping_enabled"] is not None:
                self.ping.enabled = settings["ping_enabled"]
            if settings["ping_frequency"] is not None:
                self.ping.frequency = settings["ping_frequency"]
            if settings["update_enabled"] is not None:
                self.update_enabled = settings["update_enabled"]
            if settings["update_frequency"] is not None:
                self.update_frequency = settings["update_frequency"]
            if settings["loglevel"] is not None:
                self.log_level = settings["loglevel"]
            if settings["watchdog_enabled"] is not None:
                self.watchdog_enabled = settings["watchdog_enabled"]
            if settings["watchdog_log"] is not None:
                self.watchdog_logfile = settings["watchdog_log"]
        except pymysql.err.Error:
            logger.warning("Unable to load poller (%s) config", self.node_id)

    @staticmethod
    def parse_group(g):
        if g is None:
            return [0]
        elif type(g) is int:
            return [g]
        elif type(g) is str:
            try:
                return [int(x) for x in set(g.split(","))]
            except ValueError:
                pass

        logger.error("Could not parse group string, defaulting to 0")
        return [0]


class Service:
    config = ServiceConfig()
    _fp = False
    _started = False
    start_time = 0
    queue_managers = {}
    poller_manager = None
    discovery_manager = None
    last_poll = {}
    reap_flag = False
    terminate_flag = False
    reload_flag = False
    db_failures = 0

    def __init__(self):
        self.start_time = time.time()
        self.config.populate()
        self._db = LibreNMS.DB(self.config)
        self.config.load_poller_config(self._db)

        threading.current_thread().name = self.config.name  # rename main thread
        self.attach_signals()

        self._lm = self.create_lock_manager()
        self.daily_timer = LibreNMS.RecurringTimer(
            self.config.update_frequency, self.run_maintenance, "maintenance"
        )
        self.stats_timer = LibreNMS.RecurringTimer(
            self.config.poller.frequency, self.log_performance_stats, "performance"
        )
        if self.config.watchdog_enabled:
            logger.info(
                "Starting watchdog timer for log file: {}".format(
                    self.config.watchdog_logfile
                )
            )
            self.watchdog_timer = LibreNMS.RecurringTimer(
                self.config.poller.frequency, self.logfile_watchdog, "watchdog"
            )
        else:
            logger.info("Watchdog is disabled.")
        if self.config.health_file:
            with open(self.config.health_file, "a") as f:
                utime(self.config.health_file)
        else:
            logger.info("Service health file disabled.")
        self.systemd_watchdog_timer = LibreNMS.RecurringTimer(
            10, self.systemd_watchdog, "systemd-watchdog"
        )
        self.is_master = False

    def service_age(self):
        return time.time() - self.start_time

    def attach_signals(self):
        logger.debug(
            "Attaching signal handlers on thread %s", threading.current_thread().name
        )
        signal(SIGTERM, self.terminate)  # capture sigterm and exit gracefully
        signal(SIGQUIT, self.terminate)  # capture sigquit and exit gracefully
        signal(SIGINT, self.terminate)  # capture sigint and exit gracefully
        signal(SIGHUP, self.reload)  # capture sighup and restart gracefully

        if "psutil" not in sys.modules:
            logger.warning("psutil is not available, polling gap possible")
        else:
            signal(SIGCHLD, self.reap)  # capture sigchld and reap the process

    def reap_psutil(self):
        """
        A process from a previous invocation is trying to report its status
        """
        # Speed things up by only looking at direct zombie children
        for p in psutil.Process().children(recursive=False):
            try:
                status = p.status()

                if status == psutil.STATUS_ZOMBIE:
                    pid = p.pid
                    r = os.waitpid(p.pid, os.WNOHANG)
                    logger.warning(
                        "Reaped long running job in state %s with PID %d - job returned %d",
                        status,
                        r[0],
                        r[1],
                    )
            except (OSError, psutil.NoSuchProcess):
                # process was already reaped
                continue

    def start(self):
        logger.debug("Performing startup checks...")

        if self.config.single_instance:
            self.check_single_instance()  # don't allow more than one service at a time

        if self._started:
            raise RuntimeWarning("Not allowed to start Poller twice")
        self._started = True

        logger.debug("Starting up queue managers...")

        # initialize and start the worker pools
        self.poller_manager = LibreNMS.PollerQueueManager(self.config, self._lm)
        self.queue_managers["poller"] = self.poller_manager
        self.discovery_manager = LibreNMS.DiscoveryQueueManager(self.config, self._lm)
        self.queue_managers["discovery"] = self.discovery_manager
        self.queue_managers["alerting"] = LibreNMS.AlertQueueManager(
            self.config, self._lm
        )
        self.queue_managers["services"] = LibreNMS.ServicesQueueManager(
            self.config, self._lm
        )
        self.queue_managers["billing"] = LibreNMS.BillingQueueManager(
            self.config, self._lm
        )
        self.queue_managers["ping"] = LibreNMS.PingQueueManager(self.config, self._lm)

        if self.config.update_enabled:
            self.daily_timer.start()
        self.stats_timer.start()
        self.systemd_watchdog_timer.start()
        if self.config.watchdog_enabled:
            self.watchdog_timer.start()

        logger.info("LibreNMS Service: {} started!".format(self.config.unique_name))
        logger.info(
            "Poller group {}. Using Python {} and {} locks and queues".format(
                "0 (default)" if self.config.group == [0] else self.config.group,
                python_version(),
                "redis" if isinstance(self._lm, LibreNMS.RedisLock) else "internal",
            )
        )
        logger.info(
            "Queue Workers: Discovery={} Poller={} Services={} Alerting={} Billing={} Ping={}".format(
                self.config.discovery.workers
                if self.config.discovery.enabled
                else "disabled",
                self.config.poller.workers
                if self.config.poller.enabled
                else "disabled",
                self.config.services.workers
                if self.config.services.enabled
                else "disabled",
                "enabled" if self.config.alerting.enabled else "disabled",
                "enabled" if self.config.billing.enabled else "disabled",
                "enabled" if self.config.ping.enabled else "disabled",
            )
        )

        if self.config.update_enabled:
            logger.info(
                "Maintenance tasks will be run every {}".format(
                    timedelta(seconds=self.config.update_frequency)
                )
            )
        else:
            logger.warning("Maintenance tasks are disabled.")

        # Main dispatcher loop
        try:
            while not self.terminate_flag:
                if self.reload_flag:
                    logger.info("Picked up reload flag, calling the reload process")
                    self.restart()

                if self.reap_flag:
                    self.reap_flag = False
                    self.reap_psutil()

                master_lock = self._acquire_master()
                if master_lock:
                    if not self.is_master:
                        logger.info(
                            "{} is now the master dispatcher".format(self.config.name)
                        )
                        self.is_master = True
                        self.start_dispatch_timers()

                    devices = self.fetch_immediate_device_list()
                    for device in devices:
                        device_id = device[0]
                        group = device[1]

                        if device[2]:  # polling
                            self.dispatch_immediate_polling(device_id, group)

                        if device[3]:  # discovery
                            self.dispatch_immediate_discovery(device_id, group)
                else:
                    if self.is_master:
                        logger.info(
                            "{} is no longer the master dispatcher".format(
                                self.config.name
                            )
                        )
                        self.stop_dispatch_timers()
                        self.is_master = False  # no longer master
                sleep(self.config.master_resolution)
        except KeyboardInterrupt:
            pass

        logger.info("Dispatch loop terminated")
        self.shutdown()

    def _acquire_master(self):
        return self._lm.lock(
            "dispatch.master", self.config.unique_name, self.config.master_timeout, True
        )

    def _release_master(self):
        self._lm.unlock("dispatch.master", self.config.unique_name)

    # ------------ Discovery ------------
    def dispatch_immediate_discovery(self, device_id, group):
        if not self.discovery_manager.is_locked(device_id):
            self.discovery_manager.post_work(device_id, group)

    # ------------ Polling ------------
    def dispatch_immediate_polling(self, device_id, group):
        if not self.poller_manager.is_locked(device_id):
            self.poller_manager.post_work(device_id, group)

            if self.config.debug:
                cur_time = time.time()
                elapsed = cur_time - self.last_poll.get(device_id, cur_time)
                self.last_poll[device_id] = cur_time
                # arbitrary limit to reduce spam
                if elapsed > (
                    self.config.poller.frequency - self.config.master_resolution
                ):
                    logger.debug(
                        "Dispatching polling for device {}, time since last poll {:.2f}s".format(
                            device_id, elapsed
                        )
                    )

    def fetch_immediate_device_list(self):
        try:
            poller_find_time = self.config.poller.frequency - 1
            discovery_find_time = self.config.discovery.frequency - 1

            result = self._db.query(
                """SELECT `device_id`,
                  `poller_group`,
                  IF(last_discovered IS NULL AND last_polled IS NULL, 0, COALESCE(`last_polled` <= DATE_ADD(DATE_ADD(NOW(), INTERVAL -%s SECOND), INTERVAL COALESCE(`last_polled_timetaken`, 0) SECOND), 1)) AS `poll`,
                  IF(status=0, IF(last_discovered IS NULL, 1, 0), IF (%s < `last_discovered_timetaken` * 1.25, 0, COALESCE(`last_discovered` <= DATE_ADD(DATE_ADD(NOW(), INTERVAL -%s SECOND), INTERVAL COALESCE(`last_discovered_timetaken`, 0) SECOND), 1))) AS `discover`
                FROM `devices`
                WHERE `disabled` = 0 AND (
                    `last_polled` IS NULL OR
                    `last_discovered` IS NULL OR
                    `last_polled` <= DATE_ADD(DATE_ADD(NOW(), INTERVAL -%s SECOND), INTERVAL COALESCE(`last_polled_timetaken`, 0) SECOND) OR
                    `last_discovered` <= DATE_ADD(DATE_ADD(NOW(), INTERVAL -%s SECOND), INTERVAL COALESCE(`last_discovered_timetaken`, 0) SECOND)
                )
                ORDER BY `last_polled_timetaken` DESC""",
                (
                    poller_find_time,
                    self.service_age(),
                    discovery_find_time,
                    poller_find_time,
                    discovery_find_time,
                ),
            )
            self.db_failures = 0
            return result
        except pymysql.err.Error:
            self.db_failures += 1
            if self.db_failures > self.config.max_db_failures:
                logger.warning(
                    "Too many DB failures ({}), attempting to release master".format(
                        self.db_failures
                    )
                )
                self._release_master()
                sleep(
                    self.config.master_resolution
                )  # sleep to give another node a chance to acquire
            return []

    def run_maintenance(self):
        """
        Runs update and cleanup tasks by calling daily.sh.  Reloads the python script after the update.
        Sets a schema-update lock so no distributed pollers will update until the schema has been updated.
        """
        attempt = 0
        wait = 5
        max_runtime = 86100
        max_tries = int(max_runtime / wait)
        logger.info("Waiting for schema lock")
        while not self._lm.lock("schema-update", self.config.unique_name, max_runtime):
            attempt += 1
            if attempt >= max_tries:  # don't get stuck indefinitely
                logger.warning(
                    "Reached max wait for other pollers to update, updating now"
                )
                break
            sleep(wait)

        logger.info("Running maintenance tasks")
        exit_code, output = LibreNMS.call_script("daily.sh")
        if exit_code == 0:
            logger.info("Maintenance tasks complete\n{}".format(output))
        else:
            logger.error("Error {} in daily.sh:\n{}".format(exit_code, output))

        self._lm.unlock("schema-update", self.config.unique_name)

        self.reload_flag = True

    def create_lock_manager(self):
        """
        Create a new LockManager.  Tries to create a Redis LockManager, but falls
        back to python's internal threading lock implementation.
        Exits if distributing poller is enabled and a Redis LockManager cannot be created.
        :return: Instance of LockManager
        """
        try:
            return LibreNMS.RedisLock(
                sentinel_kwargs={
                    "username": self.config.redis_sentinel_user,
                    "password": self.config.redis_sentinel_pass,
                    "socket_timeout": self.config.redis_timeout,
                    "unix_socket_path": self.config.redis_socket,
                },
                namespace="librenms.lock",
                host=self.config.redis_host,
                port=self.config.redis_port,
                db=self.config.redis_db,
                username=self.config.redis_user,
                password=self.config.redis_pass,
                unix_socket_path=self.config.redis_socket,
                sentinel=self.config.redis_sentinel,
                sentinel_service=self.config.redis_sentinel_service,
                socket_timeout=self.config.redis_timeout,
            )
        except ImportError:
            if self.config.distributed:
                logger.critical(
                    "ERROR: Redis connection required for distributed polling"
                )
                logger.critical(
                    "Please install redis-py, either through your os software repository or from PyPI"
                )
                self.exit(2)
        except Exception as e:
            if self.config.distributed:
                logger.critical(
                    "ERROR: Redis connection required for distributed polling"
                )
                logger.critical(
                    "Lock manager could not connect to Redis. {}: {}".format(
                        type(e).__name__, e
                    )
                )
                self.exit(2)

        return LibreNMS.ThreadingLock()

    def restart(self):
        """
        Stop then recreate this entire process by re-calling the original script.
        Has the effect of reloading the python files from disk.

        This should only ever be called from the main thread and never directly.
        In all other cases, set `reload_flag` to `True`.
        """
        if sys.version_info < (3, 4, 0):
            logger.warning(
                "Skipping restart as running under an incompatible interpreter"
            )
            logger.warning("Please restart manually")
            return

        logger.info("Restarting service... ")

        if "psutil" not in sys.modules:
            logger.warning("psutil is not available, polling gap possible")
            self._stop_managers_and_wait()
        else:
            self._stop_managers()
        self._release_master()

        # Set the SIGCHLD signal handler to ignore so remaining processes don't fail to report in and become zombies
        signal(SIGCHLD, SIG_IGN)
        python = sys.executable
        sys.stdout.flush()
        os.execl(python, python, *sys.argv)

    def reap(self, signalnum=None, flag=None):
        """
        Handle a set the reload flag to begin a clean restart
        :param signalnum: UNIX signal number
        :param flag: Flags accompanying signal
        """
        self.reap_flag = True

    def reload(self, signalnum=None, flag=None):
        """
        Handle a set the reload flag to begin a clean restart
        :param signalnum: UNIX signal number
        :param flag: Flags accompanying signal
        """
        logger.info(
            "Received signal on thread %s, handling", threading.current_thread().name
        )
        self.reload_flag = True

    def terminate(self, signalnum=None, flag=None):
        """
        Handle a set the terminate flag to begin a clean shutdown
        :param signalnum: UNIX signal number
        :param flag: Flags accompanying signal
        """
        logger.info(
            "Received signal on thread %s, handling", threading.current_thread().name
        )
        self.terminate_flag = True

    def shutdown(self, signalnum=None, flag=None):
        """
        Stop and exit, waiting for all child processes to exit.
        :param signalnum: UNIX signal number
        :param flag: Flags accompanying signal
        """
        logger.info("Shutting down, waiting for running jobs to complete...")

        self.stop_dispatch_timers()
        self._release_master()

        self.daily_timer.stop()
        self.stats_timer.stop()
        self.systemd_watchdog_timer.stop()
        if self.config.watchdog_enabled:
            self.watchdog_timer.stop()

        self._stop_managers_and_wait()

        # try to release master lock
        logger.info(
            "Shutdown of %s/%s complete", os.getpid(), threading.current_thread().name
        )
        self.exit(0)

    def start_dispatch_timers(self):
        """
        Start all dispatch timers and begin pushing events into queues.
        This should only be started when we are the master dispatcher.
        """
        for manager in self.queue_managers.values():
            try:
                manager.start_dispatch()
            except AttributeError:
                pass

    def stop_dispatch_timers(self):
        """
        Stop all dispatch timers, this should be called when we are no longer the master dispatcher.
        """
        for manager in self.queue_managers.values():
            try:
                manager.stop_dispatch()
            except AttributeError:
                pass

    def _stop_managers(self):
        for manager in self.queue_managers.values():
            manager.stop()

    def _stop_managers_and_wait(self):
        """
        Stop all QueueManagers, and wait for their processing threads to complete.
        We send the stop signal to all QueueManagers first, then wait for them to finish.
        """
        self._stop_managers()

        for manager in self.queue_managers.values():
            manager.stop_and_wait()

    def check_single_instance(self):
        """
        Check that there is only one instance of the service running on this computer.
        We do this be creating a file in the base directory (.lock.service) if it doesn't exist and
        obtaining an exclusive lock on that file.
        """
        lock_file = "{}/{}".format(self.config.BASE_DIR, ".lock.service")

        import fcntl

        self._fp = open(
            lock_file, "w"
        )  # keep a reference so the file handle isn't garbage collected
        self._fp.flush()
        try:
            fcntl.lockf(self._fp, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except IOError:
            logger.warning("Another instance is already running, quitting.")
            self.exit(2)

    def log_performance_stats(self):
        logger.info("Counting up time spent polling")

        try:
            # Report on the poller instance as a whole
            self._db.query(
                "INSERT INTO poller_cluster(node_id, poller_name, poller_version, poller_groups, last_report, master) "
                'values("{0}", "{1}", "{2}", "{3}", NOW(), {4}) '
                'ON DUPLICATE KEY UPDATE poller_version="{2}", last_report=NOW(), master={4}; '.format(
                    self.config.node_id,
                    self.config.name,
                    "librenms-service",
                    ",".join(str(i) for i in self.config.group),
                    1 if self.is_master else 0,
                )
            )

            # Find our ID
            self._db.query(
                'SELECT id INTO @parent_poller_id FROM poller_cluster WHERE node_id="{0}"; '.format(
                    self.config.node_id
                )
            )

            for worker_type, manager in self.queue_managers.items():
                worker_seconds, devices = manager.performance.reset()

                # Record the queue state
                self._db.query(
                    "INSERT INTO poller_cluster_stats(parent_poller, poller_type, depth, devices, worker_seconds, workers, frequency) "
                    'values(@parent_poller_id, "{0}", {1}, {2}, {3}, {4}, {5}) '
                    "ON DUPLICATE KEY UPDATE depth={1}, devices={2}, worker_seconds={3}, workers={4}, frequency={5}; ".format(
                        worker_type,
                        sum(
                            [
                                manager.get_queue(group).qsize()
                                for group in self.config.group
                            ]
                        ),
                        devices,
                        worker_seconds,
                        getattr(self.config, worker_type).workers,
                        getattr(self.config, worker_type).frequency,
                    )
                )
        except (pymysql.err.Error, ConnectionResetError, RedisConnectionError):
            logger.critical(
                "Unable to log performance statistics - is the database still online?",
                exc_info=True,
            )

    def systemd_watchdog(self):
        if self.config.health_file:
            utime(self.config.health_file)
        if "systemd.daemon" in sys.modules:
            notify("WATCHDOG=1")

    def logfile_watchdog(self):

        try:
            # check that lofgile has been written to within last poll period
            logfile_mdiff = datetime.now().timestamp() - os.path.getmtime(
                self.config.watchdog_logfile
            )
        except FileNotFoundError as e:
            logger.error("Log file not found! {}".format(e))
            return

        if logfile_mdiff > self.config.poller.frequency:
            logger.critical(
                "BARK! Log file older than {}s, restarting service!".format(
                    self.config.poller.frequency
                ),
                exc_info=True,
            )
            self.reload_flag = True
        else:
            logger.info("Log file updated {}s ago".format(int(logfile_mdiff)))

    def exit(self, code=0):
        sys.stdout.flush()
        sys.exit(code)
