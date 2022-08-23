const vm = require('vm');
const stream = require('stream');
const readline = require('readline');

const context = {
	require,
};

const references = new Map();

vm.createContext(context);

let stdout = process.stdout;
//process.stdout = new stream.WritableStream();

const rl = readline.createInterface({
	input: process.stdin
});

/**
 * Reviver function used when parsing JSON strings from PHP
 *
 * @author   Jelle De Loecker   <jelle@elevenways.be>
 * @since    0.1.1
 * @version  0.1.1
 *
 * @param    {String}   key
 * @param    {*}        value
 *
 * @return   {*}
 */
function reviver(key, value) {

	if (value && typeof value == 'object' && value['#'] === 'Doen') {

		let result,
		    type = value['#type'],
		    data = value['#data'];

		if (type == 'function') {
			result = eval('(' + data + ')');
		} else if (type == 'reference') {
			result = references.get(data);
		}

		return result;
	}

	return value;
}

/**
 * Parse the given JSON string
 *
 * @author   Jelle De Loecker   <jelle@elevenways.be>
 * @since    0.1.1
 * @version  0.1.3
 *
 * @param    {String}   str
 *
 * @return   {*}
 */
function parse(str) {

	let result;

	str = '{"__root": ' + str + '}';

	result = JSON.parse(str, reviver);

	return result.__root;
}

rl.on('line', async (data) => {

	let result,
	    error,
	    done = false,
	    code;

	data = parse(data);

	if (data.reference != null) {

		if (data.destroy) {
			references.delete(data.reference);
			return;
		}

		let instance = references.get(data.reference);

		if (data.return == 'value') {
			result = instance;
			done = true;
		} else {
			if (instance) {

				if (!instance) {
					let error_message = 'Instance not found';

					if (data.method) {
						error_message += ' unable to call "' + data.method + '" method';
					} else if (data.property) {
						error_message += ' unable to get "' + data.property + '" property';
					}

					error = new Error(error_message);
				}

				if (!error && data.method && !instance[data.method]) {

					let instance_class;

					if (instance && instance.constructor) {
						instance_class = instance.constructor.name;
					} else {
						instance_class = typeof instance;
					}

					// ElementHandle instances do not mirror all the actual element methods,
					// so we have to call those manually
					if (instance_class == 'ElementHandle') {
						done = true;
						try {
							result = instance.evaluate(`(node, args) => {
								let method = args.shift();
								return node[method](...args);
							}`);
						} catch (err) {
							error = err;
						}
					} else {
						let error_message = 'Unable to find method "' + data.method + '" on ' + instance_class + ' instance';
						error = new Error(error_message);
					}
				}

				if (done || error) {
					// Don't do the rest
				} else if (data.method) {
					try {
						result = instance[data.method].apply(instance, data.args);
					} catch (err) {
						error = err;
					}
				} else if (data.property != null) {
					try {
						result = instance[data.property];
					} catch (err) {
						error = err;
					}
				}

				done = true;
			}

			data.return = 'reference';
		}

	} else if (data.function) {
		context.args = data.args;
		code = '(' + data.function + ').apply(this, args);';
	} else {
		code = data.code;
	}

	if (!done) {
		try {
			result = vm.runInContext(code, context);

			if (result && result.then) {
				result = await result;
			}

		} catch (err) {
			error = err;
		}
	} else {
		if (result && result.then) {
			try {
				result = await result;
			} catch (err) {
				error = err;
			}
		}
	}

	if (error) {
		return sendResponse(data.id, null, error);
	}

	// We actually resolve references to their type,
	// not their value!
	if (data.return == 'reference') {
		references.set(data.id, result);
		let type = typeof result;

		if (type == 'object') {
			if (!result) {
				type = 'null';
			} else {
				if (result.constructor) {
					type = result.constructor.name;
				}
			}
		}

		result = type;
	}

	sendResponse(data.id, result);
});

function sendResponse(id, result, error) {

	if (error) {

		if (typeof error == 'object') {
			let err = error;

			error = {
				class   : null,
				name    : null,
				code    : null,
				message : '',
				stack   : null,
			};

			// We don't use `instanceof Error` because it can return false
			// due to different contexts
			if (err.constructor) {
				error.class = err.constructor.name;
			}

			if (err.name) {
				error.name = err.name;
			}

			if (err.code != null) {
				error.code = err.code;
			}

			if (err.message != null) {
				error.message = err.message;
			}

			if (err.stack) {
				error.stack = err.stack;
			}
		}
	}

	let response = {
		id,
		result,
		error
	};

	stdout.write(JSON.stringify(response) + '\n');
}