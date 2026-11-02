(function () {
    "use strict";

    var config = window.analenzorCommerceConfig;

    if (!config) {
        return;
    }

    var state = null;
    var ready = false;
    var queued = [];
    var requestChain = Promise.resolve();

    window.analenzor_c_s = [];
    window.analenzor_o = {};
    window.analenzor_l_h = "";
    window.analenzor_c_h = "";

    // Non-cryptographic fingerprint; no asynchronous hash race with senders.
    function refreshCommerceHash() {
        var orders = Object.keys(window.analenzor_o).sort().map(function (id) {
            return [id, window.analenzor_o[id]];
        });
        var text = JSON.stringify({ stages: window.analenzor_c_s, orders: orders });
        var h1 = 0xdeadbeef;
        var h2 = 0x41c6ce57;
        for (var i = 0; i < text.length; i++) {
            var code = text.charCodeAt(i);
            h1 = Math.imul(h1 ^ code, 2654435761);
            h2 = Math.imul(h2 ^ code, 1597334677);
        }
        h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^
            Math.imul(h2 ^ (h2 >>> 13), 3266489909);
        h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^
            Math.imul(h1 ^ (h1 >>> 13), 3266489909);
        window.analenzor_c_h =
            ("00000000" + (h1 >>> 0).toString(16)).slice(-8) +
            ("00000000" + (h2 >>> 0).toString(16)).slice(-8);
    }

    function rememberOrder(snapshot) {
        if (!snapshot || !snapshot.order_id) {
            return;
        }
        var id = String(snapshot.order_id);
        if (/^[1-9]\d*$/.test(id)) {
            state.orders[id] = snapshot.order_status == null
                ? null : String(snapshot.order_status);
        }
    }

    function load() {
        try {
            var value = JSON.parse(
                sessionStorage.getItem(config.storageKey)
            );

            if (
                value &&
                Array.isArray(value.stages) &&
                Array.isArray(value.observed)
            ) {
                return value;
            }
        } catch (error) {

        }

        return null;
    }

    function save() {
        if (!state) {
            return;
        }
        refreshCommerceHash();
        try {
            sessionStorage.setItem(
                config.storageKey,
                JSON.stringify(state)
            );
        } catch (error) {

        }
    }

    var MAX_EVENT_OCCURRENCES = 3;
	var MAX_HISTORY_LENGTH = 30;

	function isImportantStage(stage) {
		return stage === "fail_payment" ||
        /^(checkout_|payment_|order_)/.test(stage);
	}

	function normalizeStages(stages) {
		var result = [];
		var counts = Object.create(null);

		(Array.isArray(stages) ? stages : []).forEach(function (stage) {
			if (typeof stage !== "string" || !stage) {
				return;
			}

			// order_created نباید اولین رویداد تاریخچه باشد.
			if (!result.length && stage === "order_created") {
				return;
			}

			var length = result.length;

			// checkout_started → order_created → checkout_started
			if (
				stage === "checkout_started" &&
				length >= 2 &&
				result[length - 2] === "checkout_started" &&
				result[length - 1] === "order_created"
			) {
				stage = "fail_payment";
			}

			// checkout_started/order_created → cart_emptied → payment_confirmed
			if (
				stage === "payment_confirmed" &&
				length >= 2 &&
				(
					result[length - 2] === "checkout_started" ||
					result[length - 2] === "order_created"
				) &&
				result[length - 1] === "cart_emptied"
			) {
				result.pop();

				counts.cart_emptied = Math.max(
					0,
					(counts.cart_emptied || 0) - 1
				);
			}

			if (result[result.length - 1] === stage) {
				return;
			}

			if (!isImportantStage(stage)) {
				counts[stage] = counts[stage] || 0;

				if (counts[stage] >= MAX_EVENT_OCCURRENCES) {
					return;
				}

				counts[stage]++;
			}

			result.push(stage);
		});

		while (result.length > MAX_HISTORY_LENGTH) {
			var removableIndex = result.findIndex(function (stage) {
				return !isImportantStage(stage);
			});

			if (removableIndex === -1) {
				break;
			}

			result.splice(removableIndex, 1);
		}

		// ممکن است پس از کوتاه‌کردن تاریخچه، order_created اول قرار بگیرد.
		while (result[0] === "order_created") {
			result.shift();
		}

		return result.filter(function (stage, index) {
			return index === 0 || stage !== result[index - 1];
		});
	}


	function publish(stage, snapshot) {
		if (typeof stage !== "string" || !stage) {
			return;
		}

		var stages = normalizeStages(window.analenzor_c_s);

		// رویداد حذف‌شده را از طریق CustomEvent هم منتشر نکن.
		if (!stages.length && stage === "order_created") {
			window.analenzor_c_s = stages;
			state.stages = stages.slice();
			save();
			return;
		}

		var consecutive = stages[stages.length - 1] === stage;


		var occurrences = stages.filter(function (item) {
			return item === stage;
		}).length;

		var limited = !isImportantStage(stage) &&
			occurrences >= MAX_EVENT_OCCURRENCES;

		if (consecutive || limited) {
			window.analenzor_c_s = stages;
			state.stages = stages.slice();
			save();
			return;
		}

		// Match the event detail to the corrected array.
		var length = stages.length;

		if (
			stage === "checkout_started" &&
			length >= 2 &&
			stages[length - 2] === "checkout_started" &&
			stages[length - 1] === "order_created"
		) {
			stage = "fail_payment";
		}

		stages.push(stage);
		stages = normalizeStages(stages);

		window.analenzor_c_s = stages;
		state.stages = stages.slice();
		save();

		window.dispatchEvent(new CustomEvent("analenzor:commerce", {
			detail: {
				stage: stage,
				stages: stages.slice(),
				order: Object.assign({}, window.analenzor_o),
                hash: window.analenzor_c_h,
				cart_count: snapshot ? snapshot.cart_count : null,
				cart_empty: snapshot ? snapshot.cart_empty : null,
				timestamp: new Date().toISOString()
			}
		}));
	}




    function record(stage) {
        if (!ready) {
            queued.push(stage);
            return;
        }

        publish(stage, state.snapshot);
    }

    function observeOnce(key, stage, snapshot) {
        if (state.observed.indexOf(key) !== -1) {
            return;
        }

        state.observed.push(key);
        publish(stage, snapshot);
    }

    function applySnapshot(snapshot, initial) {
        // Login continues the guest journey; logout/account switching does not.
        var guestLogin = state && state.visitor === "guest" &&
            typeof snapshot.visitor === "string" &&
            /^user:[1-9]\d*$/.test(snapshot.visitor);

        if (!state || (state.visitor !== snapshot.visitor && !guestLogin)) {
            state = {
                visitor: snapshot.visitor,
                stages: [],
                observed: [],
                orders: {},
                snapshot: null
            };
            window.analenzor_l_h = "";
        }

        state.visitor = snapshot.visitor;
        state.stages = normalizeStages(state.stages);
		window.analenzor_c_s = state.stages.slice();


        var previous = state.snapshot;

        if (!state.orders || typeof state.orders !== "object" ||
            Array.isArray(state.orders)) {
            state.orders = {};
        }
        // Migrate the last snapshot from older storage, then apply fresh data.
        rememberOrder(previous);
        rememberOrder(snapshot);
        window.analenzor_o = Object.assign({}, state.orders);

        state.snapshot = snapshot;

        if (
			previous &&
			Array.isArray(previous.cart_items) &&
			Array.isArray(snapshot.cart_items)
		) {
			var previousItems = previous.cart_items;
			var currentItems = snapshot.cart_items;

			var hasNewItem = currentItems.some(function (item) {
				return !previousItems.some(function (oldItem) {
					return oldItem.key === item.key;
				});
			});

			var itemsChanged =
				previousItems.length !== currentItems.length ||
				currentItems.some(function (item) {
					var oldItem = previousItems.find(function (candidate) {
						return candidate.key === item.key;
					});

					return !oldItem ||
						oldItem.quantity !== item.quantity;
				});

			if (itemsChanged) {
				publish(
					snapshot.cart_empty
						? "cart_emptied"
						: hasNewItem
							? "add_to_cart"
							: "cart_updated",
					snapshot
				);
			}
		}


        if (snapshot.order_id && !snapshot.order_draft) {
            observeOnce(
                "created:" + snapshot.order_id,
                "order_created",
                snapshot
            );

            if (snapshot.order_paid) {
                observeOnce(
                    "paid:" + snapshot.order_id,
                    "payment_confirmed",
                    snapshot
                );
            } else if (snapshot.order_status === "failed") {
                observeOnce(
                    "failed:" + snapshot.order_id,
                    "payment_failed",
                    snapshot
                );
            }
        }

        ready = true;

        if (initial) {
            if (config.product) {
                publish("product_viewed", snapshot);
            }

            if (config.checkout) {
                publish("checkout_started", snapshot);
            }
        }

        while (queued.length) {
            publish(queued.shift(), snapshot);
        }

        save();
    }

    function sync(initial) {
        requestChain = requestChain.then(function () {
            var body = new URLSearchParams();

            body.set("action", "analenzor_commerce_state");
            body.set("order_id", String(config.orderId || 0));
            body.set("order_key", config.orderKey || "");

            return fetch(config.endpoint, {
                method: "POST",
                credentials: "same-origin",
                cache: "no-store",
                body: body
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error("Commerce state unavailable");
                    }

                    return response.json();
                })
                .then(function (response) {
                    if (response.success && response.data) {
                        applySnapshot(response.data, initial);
                    }
                });
        }).catch(function () {

        });

        return requestChain;
    }

    function hasAnalenzorSessionCookie() {
		return document.cookie.split(";").some(function (part) {
			var cookie = part.trim();
			var separator = cookie.indexOf("=");

			return separator !== -1 &&
				cookie.slice(0, separator) === "myjar-session" &&
				cookie.slice(separator + 1) !== "";
		});
	}

	if (hasAnalenzorSessionCookie()) {
		state = load();
	} else {
		// نشست قبلی Analenzor تمام شده است.
		try {
			sessionStorage.removeItem(config.storageKey);
		} catch (error) {
			// Storage may be unavailable.
		}

		state = null;
		ready = false;
		queued = [];

		window.analenzor_c_s = [];
		window.analenzor_o = {};
        window.analenzor_l_h = "";
        window.analenzor_c_h = "";
	}

	sync(true);


    function bind() {

        if (window.jQuery) {
            window.jQuery(document.body).on(
                "added_to_cart removed_from_cart " +
                "updated_cart_totals updated_wc_div",
                function () {
                    sync(false);
                }
            );

            window.jQuery(document.body).on(
                "checkout_place_order",
                function () {
                    record("checkout_submitted");
                }
            );

			window.jQuery(document.body).on(
				"checkout_place_order_success",
				function () {
					sync(false);
				}
			);

        }


        document.body.addEventListener(
            "wc-blocks_added_to_cart",
            function () {
                sync(false);
            }
        );

        document.body.addEventListener(
            "wc-blocks_removed_from_cart",
            function () {
                sync(false);
            }
        );


        document.addEventListener("click", function (event) {
            var target = event.target instanceof Element
                ? event.target
                : null;

            if (
                target &&
                target.closest(
                    ".wc-block-components-checkout-place-order-button"
                )
            ) {
                record("checkout_submitted");
            }
        }, true);


		if (config.checkout) {
			window.setInterval(function () {
				if (document.visibilityState === "visible") {
					sync(false);
				}
			}, 5000);
		}
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bind);
    } else {
        bind();
    }


    window.addEventListener("pageshow", function (event) {
        if (event.persisted) {
            sync(false);
        }
    });

    document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "visible") {
            sync(false);
        }
    });
}());
