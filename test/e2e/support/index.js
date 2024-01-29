
import './functions';
import chaiSorted from 'chai-sorted';
import 'cypress-audit/commands';
import './commands';
import 'cypress-real-events/support';

require('cypress-xpath');

chai.use(chaiSorted);
