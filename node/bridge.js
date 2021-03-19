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

rl.on('line', async (data) => {

	let result,
	    error,
	    done = false,
	    code;

	data = JSON.parse(data);

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

				if (!instance || !instance[data.method]) {
					console.error('Unable to find method ' + data.method, instance);
				}

				try {
					result = instance[data.method].apply(instance, data.args);
				} catch (err) {
					error = err;
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

		if (!result && type == 'object') {
			type = 'null';
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